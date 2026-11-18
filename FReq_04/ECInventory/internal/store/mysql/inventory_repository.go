package mysql

import (
	"context"
	"database/sql"
	"errors"
	"strings"

	"ecinventory/internal/domain"
)

type inventoryRepository struct {
	q queryer
}

func (r *inventoryRepository) FindStock(ctx context.Context, productID int64) (int64, bool, error) {
	var stock int64
	err := r.q.QueryRowContext(ctx, "SELECT stock FROM inventory_items WHERE product_id = ?", productID).Scan(&stock)
	if errors.Is(err, sql.ErrNoRows) {
		return 0, false, nil
	}
	if err != nil {
		return 0, false, translate(err)
	}
	return stock, true, nil
}

func (r *inventoryRepository) FindStocks(ctx context.Context, productIDs []int64) ([]domain.StockLevel, error) {
	if len(productIDs) == 0 {
		return []domain.StockLevel{}, nil
	}
	placeholders := strings.TrimSuffix(strings.Repeat("?,", len(productIDs)), ",")
	args := make([]any, len(productIDs))
	for i, id := range productIDs {
		args[i] = id
	}
	rows, err := r.q.QueryContext(ctx, "SELECT product_id, stock FROM inventory_items WHERE product_id IN ("+placeholders+") ORDER BY product_id", args...)
	if err != nil {
		return nil, translate(err)
	}
	defer rows.Close()
	levels := make([]domain.StockLevel, 0, len(productIDs))
	for rows.Next() {
		var level domain.StockLevel
		if err := rows.Scan(&level.ProductID, &level.Stock); err != nil {
			return nil, translate(err)
		}
		levels = append(levels, level)
	}
	return levels, translate(rows.Err())
}

func (r *inventoryRepository) ListAvailableIDs(ctx context.Context) ([]int64, error) {
	rows, err := r.q.QueryContext(ctx, "SELECT product_id FROM inventory_items WHERE stock > 0 ORDER BY product_id")
	if err != nil {
		return nil, translate(err)
	}
	defer rows.Close()
	ids := make([]int64, 0)
	for rows.Next() {
		var id int64
		if err := rows.Scan(&id); err != nil {
			return nil, translate(err)
		}
		ids = append(ids, id)
	}
	return ids, translate(rows.Err())
}

func (r *inventoryRepository) UpsertStock(ctx context.Context, productID, stock int64) error {
	_, err := r.q.ExecContext(ctx,
		"INSERT INTO inventory_items (product_id, stock) VALUES (?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)",
		productID, stock)
	return translate(err)
}

func (r *inventoryRepository) DeleteStock(ctx context.Context, productID int64) (bool, error) {
	result, err := r.q.ExecContext(ctx, "DELETE FROM inventory_items WHERE product_id = ?", productID)
	if err != nil {
		return false, translate(err)
	}
	affected, err := result.RowsAffected()
	return affected > 0, translate(err)
}

func (r *inventoryRepository) DecrementIfAvailable(ctx context.Context, productID, quantity int64) (bool, error) {
	result, err := r.q.ExecContext(ctx,
		"UPDATE inventory_items SET stock = stock - ? WHERE product_id = ? AND stock >= ?",
		quantity, productID, quantity)
	if err != nil {
		return false, translate(err)
	}
	affected, err := result.RowsAffected()
	return affected > 0, translate(err)
}

func (r *inventoryRepository) Increment(ctx context.Context, productID, quantity int64) error {
	_, err := r.q.ExecContext(ctx, "UPDATE inventory_items SET stock = stock + ? WHERE product_id = ?", quantity, productID)
	return translate(err)
}
