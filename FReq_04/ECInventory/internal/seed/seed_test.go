package seed

import (
	"context"
	"errors"
	"testing"

	"ecinventory/internal/domain"
	"ecinventory/internal/service"
)

type fakeSource struct {
	ids []int64
	err error
}

func (f fakeSource) ProductIDs(context.Context) ([]int64, error) { return f.ids, f.err }

type fakeInventory struct {
	stock map[int64]int64
}

func (f *fakeInventory) FindStock(_ context.Context, id int64) (int64, bool, error) {
	v, ok := f.stock[id]
	return v, ok, nil
}
func (f *fakeInventory) FindStocks(context.Context, []int64) ([]domain.StockLevel, error) {
	return nil, nil
}
func (f *fakeInventory) ListAvailableIDs(context.Context) ([]int64, error) { return nil, nil }
func (f *fakeInventory) UpsertStock(_ context.Context, id, stock int64) error {
	f.stock[id] = stock
	return nil
}
func (f *fakeInventory) DeleteStock(context.Context, int64) (bool, error) { return false, nil }
func (f *fakeInventory) DecrementIfAvailable(context.Context, int64, int64) (bool, error) {
	return false, nil
}
func (f *fakeInventory) Increment(context.Context, int64, int64) error { return nil }

type fakeReservations struct{}

func (fakeReservations) FindByReservationID(context.Context, string) ([]domain.ReservationRow, error) {
	return nil, nil
}
func (fakeReservations) InsertMany(context.Context, string, []domain.Item) error { return nil }
func (fakeReservations) MarkReleased(context.Context, string, int64) (bool, error) {
	return false, nil
}

type fakeTarget struct {
	inventory *fakeInventory
	resets    int
}

func newTarget() *fakeTarget {
	return &fakeTarget{inventory: &fakeInventory{stock: map[int64]int64{99: 5}}}
}

func (f *fakeTarget) Reset(context.Context) error {
	f.resets++
	f.inventory.stock = map[int64]int64{}
	return nil
}
func (f *fakeTarget) Repositories() service.Repositories {
	return service.Repositories{Inventory: f.inventory, Reservations: fakeReservations{}}
}
func (f *fakeTarget) Ping(context.Context) error { return nil }
func (f *fakeTarget) InTransaction(_ context.Context, work func(service.Repositories) error) error {
	return work(f.Repositories())
}

func TestRunWipesAndFillsEveryProduct(t *testing.T) {
	target := newTarget()
	result, err := Run(context.Background(), fakeSource{ids: []int64{1, 2, 3}}, target, Options{Min: 10, Max: 20, Seed: 1})
	if err != nil {
		t.Fatal(err)
	}
	if target.resets != 1 || result.Products != 3 || len(target.inventory.stock) != 3 {
		t.Fatalf("unexpected state: %+v %v", result, target.inventory.stock)
	}
	if _, stale := target.inventory.stock[99]; stale {
		t.Fatal("stale record survived the reset")
	}
	var total int64
	for id, quantity := range target.inventory.stock {
		if quantity < 10 || quantity > 20 {
			t.Fatalf("product %d quantity %d out of range", id, quantity)
		}
		total += quantity
	}
	if total != result.Units {
		t.Fatalf("units mismatch: %d vs %d", total, result.Units)
	}
}

func TestRunIsDeterministicForSameSeed(t *testing.T) {
	first, second := newTarget(), newTarget()
	source := fakeSource{ids: []int64{1, 2, 3, 4}}
	options := Options{Min: 1, Max: 500, Seed: 7}
	if _, err := Run(context.Background(), source, first, options); err != nil {
		t.Fatal(err)
	}
	if _, err := Run(context.Background(), source, second, options); err != nil {
		t.Fatal(err)
	}
	for id, quantity := range first.inventory.stock {
		if second.inventory.stock[id] != quantity {
			t.Fatalf("product %d differs", id)
		}
	}
}

func TestRunWithoutProductsKeepsInventoryUntouched(t *testing.T) {
	target := newTarget()
	_, err := Run(context.Background(), fakeSource{}, target, Options{Min: 1, Max: 2})
	if !errors.Is(err, ErrNoProducts) {
		t.Fatalf("expected ErrNoProducts, got %v", err)
	}
	if target.resets != 0 || target.inventory.stock[99] != 5 {
		t.Fatal("inventory was modified")
	}
}

func TestRunRejectsInvalidRange(t *testing.T) {
	if _, err := Run(context.Background(), fakeSource{ids: []int64{1}}, newTarget(), Options{Min: 5, Max: 1}); err == nil {
		t.Fatal("expected error")
	}
	if _, err := Run(context.Background(), fakeSource{ids: []int64{1}}, newTarget(), Options{Min: -1, Max: 1}); err == nil {
		t.Fatal("expected error")
	}
}
