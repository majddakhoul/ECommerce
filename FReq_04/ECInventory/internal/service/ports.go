package service

import (
	"context"

	"ecinventory/internal/domain"
)

type InventoryRepository interface {
	FindStock(ctx context.Context, productID int64) (int64, bool, error)
	FindStocks(ctx context.Context, productIDs []int64) ([]domain.StockLevel, error)
	ListAvailableIDs(ctx context.Context) ([]int64, error)
	UpsertStock(ctx context.Context, productID, stock int64) error
	DeleteStock(ctx context.Context, productID int64) (bool, error)
	DecrementIfAvailable(ctx context.Context, productID, quantity int64) (bool, error)
	Increment(ctx context.Context, productID, quantity int64) error
}

type ReservationRepository interface {
	FindByReservationID(ctx context.Context, reservationID string) ([]domain.ReservationRow, error)
	InsertMany(ctx context.Context, reservationID string, items []domain.Item) error
	MarkReleased(ctx context.Context, reservationID string, productID int64) (bool, error)
}

type Repositories struct {
	Inventory    InventoryRepository
	Reservations ReservationRepository
}

type Store interface {
	Repositories() Repositories
	Ping(ctx context.Context) error
	InTransaction(ctx context.Context, work func(Repositories) error) error
}
