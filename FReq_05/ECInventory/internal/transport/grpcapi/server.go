package grpcapi

import (
	"context"

	"google.golang.org/grpc"

	"ecinventory/gen/inventorypb"
	"ecinventory/internal/domain"
	"ecinventory/internal/service"
)

type Server struct {
	inventorypb.UnimplementedInventoryServiceServer
	inventory *service.Inventory
}

func New(inventory *service.Inventory, apiKey string) *grpc.Server {
	server := grpc.NewServer(grpc.ChainUnaryInterceptor(Recovery(), Logging(), Authentication(apiKey), PublishEvents()))
	inventorypb.RegisterInventoryServiceServer(server, &Server{inventory: inventory})
	return server
}

func (s *Server) Ping(ctx context.Context, _ *inventorypb.PingRequest) (*inventorypb.PingResponse, error) {
	if err := s.inventory.Ping(ctx); err != nil {
		return nil, toStatus(err)
	}
	return &inventorypb.PingResponse{Ok: true}, nil
}

func (s *Server) GetStock(ctx context.Context, req *inventorypb.GetStockRequest) (*inventorypb.GetStockResponse, error) {
	stock, found, err := s.inventory.GetStock(ctx, req.GetProductId())
	if err != nil {
		return nil, toStatus(err)
	}
	return &inventorypb.GetStockResponse{Found: found, Stock: stock}, nil
}

func (s *Server) GetStocks(ctx context.Context, req *inventorypb.GetStocksRequest) (*inventorypb.GetStocksResponse, error) {
	levels, err := s.inventory.GetStocks(ctx, req.GetProductIds())
	if err != nil {
		return nil, toStatus(err)
	}
	response := &inventorypb.GetStocksResponse{Levels: make([]*inventorypb.StockLevel, 0, len(levels))}
	for _, level := range levels {
		response.Levels = append(response.Levels, &inventorypb.StockLevel{ProductId: level.ProductID, Stock: level.Stock})
	}
	return response, nil
}

func (s *Server) ListAvailableProductIds(ctx context.Context, _ *inventorypb.ListAvailableProductIdsRequest) (*inventorypb.ListAvailableProductIdsResponse, error) {
	ids, err := s.inventory.ListAvailableProductIDs(ctx)
	if err != nil {
		return nil, toStatus(err)
	}
	return &inventorypb.ListAvailableProductIdsResponse{ProductIds: ids}, nil
}

func (s *Server) SetStock(ctx context.Context, req *inventorypb.SetStockRequest) (*inventorypb.SetStockResponse, error) {
	stock, err := s.inventory.SetStock(ctx, req.GetProductId(), req.GetStock())
	if err != nil {
		return nil, toStatus(err)
	}
	return &inventorypb.SetStockResponse{Stock: stock}, nil
}

func (s *Server) DeleteStock(ctx context.Context, req *inventorypb.DeleteStockRequest) (*inventorypb.DeleteStockResponse, error) {
	deleted, err := s.inventory.DeleteStock(ctx, req.GetProductId())
	if err != nil {
		return nil, toStatus(err)
	}
	return &inventorypb.DeleteStockResponse{Deleted: deleted}, nil
}

func (s *Server) ReserveStock(ctx context.Context, req *inventorypb.ReserveStockRequest) (*inventorypb.ReserveStockResponse, error) {
	items := make([]domain.Item, 0, len(req.GetItems()))
	for _, item := range req.GetItems() {
		items = append(items, domain.Item{ProductID: item.GetProductId(), Quantity: item.GetQuantity()})
	}
	result, err := s.inventory.Reserve(ctx, req.GetReservationId(), items)
	if err != nil {
		return nil, toStatus(err)
	}
	response := &inventorypb.ReserveStockResponse{
		Reserved:      result.Reserved,
		ReservationId: result.ReservationID,
		Shortages:     make([]*inventorypb.StockShortage, 0, len(result.Shortages)),
	}
	for _, shortage := range result.Shortages {
		response.Shortages = append(response.Shortages, &inventorypb.StockShortage{
			ProductId: shortage.ProductID,
			Requested: shortage.Requested,
			Available: shortage.Available,
			Reason:    toPBReason(shortage.Reason),
		})
	}
	return response, nil
}

func (s *Server) ReleaseStock(ctx context.Context, req *inventorypb.ReleaseStockRequest) (*inventorypb.ReleaseStockResponse, error) {
	result, err := s.inventory.Release(ctx, req.GetReservationId())
	if err != nil {
		return nil, toStatus(err)
	}
	return &inventorypb.ReleaseStockResponse{Released: result.Released, ItemsReleased: result.ItemsReleased}, nil
}

func toPBReason(reason domain.ShortageReason) inventorypb.ShortageReason {
	if reason == domain.ReasonProductNotFound {
		return inventorypb.ShortageReason_PRODUCT_NOT_FOUND
	}
	return inventorypb.ShortageReason_INSUFFICIENT_STOCK
}
