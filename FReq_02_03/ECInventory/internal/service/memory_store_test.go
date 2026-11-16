package service_test

import (
	"context"
	"sort"
	"sync"

	"ecinventory/internal/domain"
	"ecinventory/internal/service"
)

type memoryStore struct {
	mu           sync.Mutex
	stock        map[int64]int64
	reservations map[string][]domain.ReservationRow
	failInsert   error
}

func newMemoryStore() *memoryStore {
	return &memoryStore{stock: map[int64]int64{}, reservations: map[string][]domain.ReservationRow{}}
}

func (m *memoryStore) Ping(context.Context) error { return nil }

func (m *memoryStore) Repositories() service.Repositories {
	return service.Repositories{Inventory: &memoryInventory{m: m, lock: true}, Reservations: &memoryReservations{m: m, lock: true}}
}

func (m *memoryStore) transactional() service.Repositories {
	return service.Repositories{Inventory: &memoryInventory{m: m}, Reservations: &memoryReservations{m: m}}
}

func (m *memoryStore) InTransaction(_ context.Context, work func(service.Repositories) error) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	stockSnapshot := make(map[int64]int64, len(m.stock))
	for id, value := range m.stock {
		stockSnapshot[id] = value
	}
	reservationSnapshot := make(map[string][]domain.ReservationRow, len(m.reservations))
	for id, rows := range m.reservations {
		reservationSnapshot[id] = append([]domain.ReservationRow(nil), rows...)
	}
	if err := work(m.transactional()); err != nil {
		m.stock = stockSnapshot
		m.reservations = reservationSnapshot
		return err
	}
	return nil
}

type memoryInventory struct {
	m    *memoryStore
	lock bool
}

func (r *memoryInventory) guard() func() {
	if !r.lock {
		return func() {}
	}
	r.m.mu.Lock()
	return r.m.mu.Unlock
}

func (r *memoryInventory) FindStock(_ context.Context, id int64) (int64, bool, error) {
	defer r.guard()()
	stock, ok := r.m.stock[id]
	return stock, ok, nil
}

func (r *memoryInventory) FindStocks(_ context.Context, ids []int64) ([]domain.StockLevel, error) {
	defer r.guard()()
	levels := []domain.StockLevel{}
	for _, id := range ids {
		if stock, ok := r.m.stock[id]; ok {
			levels = append(levels, domain.StockLevel{ProductID: id, Stock: stock})
		}
	}
	sort.Slice(levels, func(i, j int) bool { return levels[i].ProductID < levels[j].ProductID })
	return levels, nil
}

func (r *memoryInventory) ListAvailableIDs(context.Context) ([]int64, error) {
	defer r.guard()()
	ids := []int64{}
	for id, stock := range r.m.stock {
		if stock > 0 {
			ids = append(ids, id)
		}
	}
	sort.Slice(ids, func(i, j int) bool { return ids[i] < ids[j] })
	return ids, nil
}

func (r *memoryInventory) UpsertStock(_ context.Context, id, stock int64) error {
	defer r.guard()()
	r.m.stock[id] = stock
	return nil
}

func (r *memoryInventory) DeleteStock(_ context.Context, id int64) (bool, error) {
	defer r.guard()()
	_, ok := r.m.stock[id]
	delete(r.m.stock, id)
	return ok, nil
}

func (r *memoryInventory) DecrementIfAvailable(_ context.Context, id, quantity int64) (bool, error) {
	defer r.guard()()
	if stock, ok := r.m.stock[id]; ok && stock >= quantity {
		r.m.stock[id] = stock - quantity
		return true, nil
	}
	return false, nil
}

func (r *memoryInventory) Increment(_ context.Context, id, quantity int64) error {
	defer r.guard()()
	if _, ok := r.m.stock[id]; ok {
		r.m.stock[id] += quantity
	}
	return nil
}

type memoryReservations struct {
	m    *memoryStore
	lock bool
}

func (r *memoryReservations) guard() func() {
	if !r.lock {
		return func() {}
	}
	r.m.mu.Lock()
	return r.m.mu.Unlock
}

func (r *memoryReservations) FindByReservationID(_ context.Context, id string) ([]domain.ReservationRow, error) {
	defer r.guard()()
	return append([]domain.ReservationRow(nil), r.m.reservations[id]...), nil
}

func (r *memoryReservations) InsertMany(_ context.Context, id string, items []domain.Item) error {
	defer r.guard()()
	if r.m.failInsert != nil {
		return r.m.failInsert
	}
	for _, item := range items {
		r.m.reservations[id] = append(r.m.reservations[id], domain.ReservationRow{
			ProductID: item.ProductID, Quantity: item.Quantity, Status: domain.StatusReserved,
		})
	}
	return nil
}

func (r *memoryReservations) MarkReleased(_ context.Context, id string, productID int64) (bool, error) {
	defer r.guard()()
	rows := r.m.reservations[id]
	for i := range rows {
		if rows[i].ProductID == productID && rows[i].Status == domain.StatusReserved {
			rows[i].Status = domain.StatusReleased
			return true, nil
		}
	}
	return false, nil
}
