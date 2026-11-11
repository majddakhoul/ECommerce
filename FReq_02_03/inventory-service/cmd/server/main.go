package main

import (
	"context"
	"database/sql"
	"errors"
	"log"
	"net"
	"os"
	"sort"
	"strings"
	"time"

	inventoryv1 "github.com/ecommerce/inventory-service"
	_ "github.com/go-sql-driver/mysql"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type server struct {
	inventoryv1.UnimplementedInventoryServiceServer
	db *sql.DB
}

func (s *server) GetProduct(ctx context.Context, req *inventoryv1.GetProductRequest) (*inventoryv1.ProductResponse, error) {
	var p inventoryv1.ProductResponse
	err := s.db.QueryRowContext(ctx, `SELECT product_id, name, stock FROM inventory_products WHERE product_id = ?`, req.GetProductId()).Scan(&p.ProductId, &p.Name, &p.Stock)
	if errors.Is(err, sql.ErrNoRows) {
		return nil, status.Error(codes.NotFound, "product not found")
	}
	if err != nil {
		return nil, status.Error(codes.Internal, "inventory read failed")
	}
	return &p, nil
}

func (s *server) EnsureProduct(ctx context.Context, req *inventoryv1.EnsureProductRequest) (*inventoryv1.ProductResponse, error) {
	_, err := s.db.ExecContext(ctx, `INSERT INTO inventory_products(product_id,name,stock) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)`, req.GetProductId(), req.GetName(), req.GetStock())
	if err != nil {
		return nil, status.Error(codes.Internal, "inventory product sync failed")
	}
	return s.GetProduct(ctx, &inventoryv1.GetProductRequest{ProductId: req.GetProductId()})
}

func (s *server) SetStock(ctx context.Context, req *inventoryv1.SetStockRequest) (*inventoryv1.ProductResponse, error) {
	if req.GetStock() < 0 {
		return nil, status.Error(codes.InvalidArgument, "stock cannot be negative")
	}
	result, err := s.db.ExecContext(ctx, `UPDATE inventory_products SET stock=? WHERE product_id=?`, req.GetStock(), req.GetProductId())
	if err != nil {
		return nil, status.Error(codes.Internal, "inventory update failed")
	}
	n, _ := result.RowsAffected()
	if n == 0 {
		return nil, status.Error(codes.NotFound, "product not found")
	}
	return s.GetProduct(ctx, &inventoryv1.GetProductRequest{ProductId: req.GetProductId()})
}

func (s *server) Reserve(ctx context.Context, req *inventoryv1.ReserveRequest) (*inventoryv1.ReserveResponse, error) {
	if strings.TrimSpace(req.GetReservationId()) == "" || len(req.GetItems()) == 0 {
		return nil, status.Error(codes.InvalidArgument, "reservation_id and items are required")
	}
	items := append([]*inventoryv1.ReservationItem(nil), req.GetItems()...)
	sort.Slice(items, func(i, j int) bool { return items[i].GetProductId() < items[j].GetProductId() })
	tx, err := s.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelSerializable})
	if err != nil {
		return nil, status.Error(codes.Internal, "reservation transaction failed")
	}
	defer tx.Rollback()
	var existing string
	err = tx.QueryRowContext(ctx, `SELECT reservation_id FROM inventory_reservations WHERE reservation_id=?`, req.GetReservationId()).Scan(&existing)
	if err == nil {
		return &inventoryv1.ReserveResponse{Reserved: true, Message: "reservation already applied"}, nil
	}
	if !errors.Is(err, sql.ErrNoRows) {
		return nil, status.Error(codes.Internal, "reservation lookup failed")
	}
	for _, item := range items {
		if item.GetProductId() <= 0 || item.GetQuantity() <= 0 {
			return nil, status.Error(codes.InvalidArgument, "product_id and quantity must be positive")
		}
		var stock int64
		err = tx.QueryRowContext(ctx, `SELECT stock FROM inventory_products WHERE product_id=? FOR UPDATE`, item.GetProductId()).Scan(&stock)
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Errorf(codes.NotFound, "product %d not found", item.GetProductId())
		}
		if err != nil {
			return nil, status.Error(codes.Internal, "inventory lock failed")
		}
		if stock < item.GetQuantity() {
			return nil, status.Errorf(codes.FailedPrecondition, "insufficient stock for product %d", item.GetProductId())
		}
	}
	for _, item := range items {
		if _, err = tx.ExecContext(ctx, `UPDATE inventory_products SET stock=stock-? WHERE product_id=?`, item.GetQuantity(), item.GetProductId()); err != nil {
			return nil, status.Error(codes.Internal, "inventory decrement failed")
		}
		if _, err = tx.ExecContext(ctx, `INSERT INTO inventory_reservation_items(reservation_id,product_id,quantity) VALUES(?,?,?)`, req.GetReservationId(), item.GetProductId(), item.GetQuantity()); err != nil {
			return nil, status.Error(codes.Internal, "reservation item write failed")
		}
	}
	if _, err = tx.ExecContext(ctx, `INSERT INTO inventory_reservations(reservation_id,status) VALUES(?, 'reserved')`, req.GetReservationId()); err != nil {
		return nil, status.Error(codes.Internal, "reservation write failed")
	}
	if err = tx.Commit(); err != nil {
		return nil, status.Error(codes.Aborted, "reservation commit failed")
	}
	return &inventoryv1.ReserveResponse{Reserved: true, Message: "inventory reserved"}, nil
}

func (s *server) Release(ctx context.Context, req *inventoryv1.ReleaseRequest) (*inventoryv1.ReleaseResponse, error) {
	if strings.TrimSpace(req.GetReservationId()) == "" {
		return nil, status.Error(codes.InvalidArgument, "reservation_id is required")
	}
	tx, err := s.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelSerializable})
	if err != nil {
		return nil, status.Error(codes.Internal, "release transaction failed")
	}
	defer tx.Rollback()
	var state string
	err = tx.QueryRowContext(ctx, `SELECT status FROM inventory_reservations WHERE reservation_id=? FOR UPDATE`, req.GetReservationId()).Scan(&state)
	if errors.Is(err, sql.ErrNoRows) {
		return &inventoryv1.ReleaseResponse{Released: true, Message: "reservation already absent"}, nil
	}
	if err != nil {
		return nil, status.Error(codes.Internal, "release lookup failed")
	}
	if state == "released" {
		return &inventoryv1.ReleaseResponse{Released: true, Message: "reservation already released"}, nil
	}
	rows, err := tx.QueryContext(ctx, `SELECT product_id, quantity FROM inventory_reservation_items WHERE reservation_id=?`, req.GetReservationId())
	if err != nil {
		return nil, status.Error(codes.Internal, "release items lookup failed")
	}
	defer rows.Close()
	for rows.Next() {
		var id, quantity int64
		if err = rows.Scan(&id, &quantity); err != nil {
			return nil, status.Error(codes.Internal, "release item scan failed")
		}
		if _, err = tx.ExecContext(ctx, `UPDATE inventory_products SET stock=stock+? WHERE product_id=?`, quantity, id); err != nil {
			return nil, status.Error(codes.Internal, "inventory restore failed")
		}
	}
	if err = rows.Err(); err != nil {
		return nil, status.Error(codes.Internal, "release items read failed")
	}
	if _, err = tx.ExecContext(ctx, `UPDATE inventory_reservations SET status='released', released_at=NOW() WHERE reservation_id=?`, req.GetReservationId()); err != nil {
		return nil, status.Error(codes.Internal, "release state update failed")
	}
	if err = tx.Commit(); err != nil {
		return nil, status.Error(codes.Aborted, "release commit failed")
	}
	return &inventoryv1.ReleaseResponse{Released: true, Message: "inventory released"}, nil
}

func (s *server) Health(context.Context, *inventoryv1.HealthRequest) (*inventoryv1.HealthResponse, error) {
	if err := s.db.Ping(); err != nil {
		return nil, status.Error(codes.Unavailable, "database unavailable")
	}
	return &inventoryv1.HealthResponse{Status: "ok"}, nil
}

func main() {
	addr := getenv("GRPC_ADDR", ":50051")
	dsn := getenv("INVENTORY_DSN", "inventory:secret@tcp(127.0.0.1:3306)/inventory?parseTime=true")
	var db *sql.DB
	var err error
	for i := 0; i < 30; i++ {
		db, err = sql.Open("mysql", dsn)
		if err == nil {
			err = db.Ping()
			if err == nil {
				break
			}
		}
		time.Sleep(2 * time.Second)
	}
	if err != nil {
		log.Fatalf("inventory database unavailable: %v", err)
	}
	defer db.Close()
	if err = bootstrap(db); err != nil {
		log.Fatal(err)
	}
	lis, err := net.Listen("tcp", addr)
	if err != nil {
		log.Fatal(err)
	}
	grpcServer := grpc.NewServer()
	inventoryv1.RegisterInventoryServiceServer(grpcServer, &server{db: db})
	log.Printf("inventory gRPC listening on %s", addr)
	if err = grpcServer.Serve(lis); err != nil {
		log.Fatal(err)
	}
}
func bootstrap(db *sql.DB) error {
	statements := []string{
		`CREATE TABLE IF NOT EXISTS inventory_products (product_id BIGINT PRIMARY KEY, name VARCHAR(255) NOT NULL, stock BIGINT UNSIGNED NOT NULL DEFAULT 0, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)`,
		`CREATE TABLE IF NOT EXISTS inventory_reservations (reservation_id VARCHAR(100) PRIMARY KEY, status ENUM('reserved','released') NOT NULL, released_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)`,
		`CREATE TABLE IF NOT EXISTS inventory_reservation_items (reservation_id VARCHAR(100) NOT NULL, product_id BIGINT NOT NULL, quantity BIGINT UNSIGNED NOT NULL, PRIMARY KEY(reservation_id,product_id), CONSTRAINT fk_reservation FOREIGN KEY(reservation_id) REFERENCES inventory_reservations(reservation_id) ON DELETE CASCADE)`,
	}
	for _, statement := range statements {
		if _, err := db.Exec(statement); err != nil {
			return err
		}
	}
	return nil
}
func getenv(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}
