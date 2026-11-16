package service

import (
	"context"
	"errors"

	"ecinventory/internal/domain"
	"ecinventory/internal/validation"
)

type Inventory struct {
	store Store
}

func NewInventory(store Store) *Inventory {
	return &Inventory{store: store}
}

func (s *Inventory) Ping(ctx context.Context) error {
	return s.store.Ping(ctx)
}

func (s *Inventory) GetStock(ctx context.Context, productID int64) (int64, bool, error) {
	id, err := validation.PositiveID(productID, "product_id")
	if err != nil {
		return 0, false, err
	}
	return s.store.Repositories().Inventory.FindStock(ctx, id)
}

func (s *Inventory) GetStocks(ctx context.Context, productIDs []int64) ([]domain.StockLevel, error) {
	ids, err := validation.UniqueIDs(productIDs)
	if err != nil {
		return nil, err
	}
	if len(ids) == 0 {
		return []domain.StockLevel{}, nil
	}
	return s.store.Repositories().Inventory.FindStocks(ctx, ids)
}

func (s *Inventory) ListAvailableProductIDs(ctx context.Context) ([]int64, error) {
	return s.store.Repositories().Inventory.ListAvailableIDs(ctx)
}

func (s *Inventory) SetStock(ctx context.Context, productID, stock int64) (int64, error) {
	id, err := validation.PositiveID(productID, "product_id")
	if err != nil {
		return 0, err
	}
	quantity, err := validation.Quantity(stock, "stock", true)
	if err != nil {
		return 0, err
	}
	if err := s.store.Repositories().Inventory.UpsertStock(ctx, id, quantity); err != nil {
		return 0, err
	}
	return quantity, nil
}

func (s *Inventory) DeleteStock(ctx context.Context, productID int64) (bool, error) {
	id, err := validation.PositiveID(productID, "product_id")
	if err != nil {
		return false, err
	}
	return s.store.Repositories().Inventory.DeleteStock(ctx, id)
}

func (s *Inventory) Reserve(ctx context.Context, reservationID string, items []domain.Item) (domain.ReservationResult, error) {
	id, err := validation.ReservationID(reservationID)
	if err != nil {
		return domain.ReservationResult{}, err
	}
	merged, err := validation.MergeItems(items)
	if err != nil {
		return domain.ReservationResult{}, err
	}

	result, err := s.reserveWithRetry(ctx, id, merged)
	if errors.Is(err, domain.ErrRejected) {
		shortages, describeErr := s.describeShortages(ctx, merged)
		if describeErr != nil {
			return domain.ReservationResult{}, describeErr
		}
		return domain.ReservationResult{Reserved: false, ReservationID: id, Shortages: shortages}, nil
	}
	return result, err
}

func (s *Inventory) reserveWithRetry(ctx context.Context, id string, items []domain.Item) (domain.ReservationResult, error) {
	result, err := s.reserveOnce(ctx, id, items)
	if errors.Is(err, domain.ErrDuplicate) {
		return s.reserveOnce(ctx, id, items)
	}
	return result, err
}

func (s *Inventory) reserveOnce(ctx context.Context, id string, items []domain.Item) (domain.ReservationResult, error) {
	var result domain.ReservationResult
	err := s.store.InTransaction(ctx, func(repos Repositories) error {
		existing, err := repos.Reservations.FindByReservationID(ctx, id)
		if err != nil {
			return err
		}
		if len(existing) > 0 {
			reserved := true
			for _, row := range existing {
				if row.Status != domain.StatusReserved {
					reserved = false
				}
			}
			result = domain.ReservationResult{Reserved: reserved, ReservationID: id}
			return nil
		}
		for _, item := range items {
			taken, err := repos.Inventory.DecrementIfAvailable(ctx, item.ProductID, item.Quantity)
			if err != nil {
				return err
			}
			if !taken {
				return domain.ErrRejected
			}
		}
		if err := repos.Reservations.InsertMany(ctx, id, items); err != nil {
			return err
		}
		result = domain.ReservationResult{Reserved: true, ReservationID: id}
		return nil
	})
	return result, err
}

func (s *Inventory) describeShortages(ctx context.Context, items []domain.Item) ([]domain.Shortage, error) {
	ids := make([]int64, 0, len(items))
	for _, item := range items {
		ids = append(ids, item.ProductID)
	}
	levels, err := s.store.Repositories().Inventory.FindStocks(ctx, ids)
	if err != nil {
		return nil, err
	}
	stockByID := make(map[int64]int64, len(levels))
	for _, level := range levels {
		stockByID[level.ProductID] = level.Stock
	}
	shortages := make([]domain.Shortage, 0)
	for _, item := range items {
		available, found := stockByID[item.ProductID]
		if found && available >= item.Quantity {
			continue
		}
		reason := domain.ReasonProductNotFound
		if found {
			reason = domain.ReasonInsufficientStock
		}
		shortages = append(shortages, domain.Shortage{
			ProductID: item.ProductID,
			Requested: item.Quantity,
			Available: available,
			Reason:    reason,
		})
	}
	return shortages, nil
}

func (s *Inventory) Release(ctx context.Context, reservationID string) (domain.ReleaseResult, error) {
	id, err := validation.ReservationID(reservationID)
	if err != nil {
		return domain.ReleaseResult{}, err
	}
	var released int64
	err = s.store.InTransaction(ctx, func(repos Repositories) error {
		released = 0
		rows, err := repos.Reservations.FindByReservationID(ctx, id)
		if err != nil {
			return err
		}
		for _, row := range rows {
			if row.Status != domain.StatusReserved {
				continue
			}
			marked, err := repos.Reservations.MarkReleased(ctx, id, row.ProductID)
			if err != nil {
				return err
			}
			if !marked {
				continue
			}
			if err := repos.Inventory.Increment(ctx, row.ProductID, row.Quantity); err != nil {
				return err
			}
			released++
		}
		return nil
	})
	if err != nil {
		return domain.ReleaseResult{}, err
	}
	return domain.ReleaseResult{Released: released > 0, ItemsReleased: released}, nil
}
