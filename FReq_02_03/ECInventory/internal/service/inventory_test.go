package service_test

import (
	"context"
	"errors"
	"sync"
	"testing"

	"ecinventory/internal/domain"
	"ecinventory/internal/service"
)

func newService(stock map[int64]int64) (*service.Inventory, *memoryStore) {
	store := newMemoryStore()
	for id, value := range stock {
		store.stock[id] = value
	}
	return service.NewInventory(store), store
}

func TestSetAndGetStock(t *testing.T) {
	svc, _ := newService(nil)
	ctx := context.Background()

	if _, err := svc.SetStock(ctx, 7, 12); err != nil {
		t.Fatal(err)
	}
	stock, found, err := svc.GetStock(ctx, 7)
	if err != nil || !found || stock != 12 {
		t.Fatalf("unexpected result stock=%d found=%v err=%v", stock, found, err)
	}
	_, found, _ = svc.GetStock(ctx, 8)
	if found {
		t.Fatal("unknown product must not be found")
	}
}

func TestGetStocksDeduplicatesIDs(t *testing.T) {
	svc, _ := newService(map[int64]int64{1: 5, 2: 6})
	levels, err := svc.GetStocks(context.Background(), []int64{2, 1, 2, 99})
	if err != nil {
		t.Fatal(err)
	}
	if len(levels) != 2 || levels[0].ProductID != 1 || levels[1].ProductID != 2 {
		t.Fatalf("unexpected levels %+v", levels)
	}
}

func TestListAvailableSkipsEmptyStock(t *testing.T) {
	svc, _ := newService(map[int64]int64{1: 0, 2: 3, 3: 1})
	ids, err := svc.ListAvailableProductIDs(context.Background())
	if err != nil || len(ids) != 2 || ids[0] != 2 || ids[1] != 3 {
		t.Fatalf("unexpected ids %v err=%v", ids, err)
	}
}

func TestDeleteStock(t *testing.T) {
	svc, _ := newService(map[int64]int64{4: 1})
	deleted, err := svc.DeleteStock(context.Background(), 4)
	if err != nil || !deleted {
		t.Fatalf("expected deletion, got %v %v", deleted, err)
	}
	deleted, _ = svc.DeleteStock(context.Background(), 4)
	if deleted {
		t.Fatal("second deletion must report false")
	}
}

func TestReserveSuccessDecrementsStock(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 5, 2: 5})
	result, err := svc.Reserve(context.Background(), "order-1", []domain.Item{{ProductID: 1, Quantity: 2}, {ProductID: 2, Quantity: 5}})
	if err != nil || !result.Reserved {
		t.Fatalf("expected reservation, got %+v %v", result, err)
	}
	if store.stock[1] != 3 || store.stock[2] != 0 {
		t.Fatalf("unexpected stock %+v", store.stock)
	}
}

func TestReserveMergesDuplicateItems(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 5})
	result, err := svc.Reserve(context.Background(), "order-1", []domain.Item{{ProductID: 1, Quantity: 2}, {ProductID: 1, Quantity: 3}})
	if err != nil || !result.Reserved || store.stock[1] != 0 {
		t.Fatalf("unexpected %+v %v stock=%v", result, err, store.stock)
	}
}

func TestReserveIsAllOrNothing(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 5, 2: 1})
	result, err := svc.Reserve(context.Background(), "order-1", []domain.Item{{ProductID: 1, Quantity: 2}, {ProductID: 2, Quantity: 3}})
	if err != nil || result.Reserved {
		t.Fatalf("expected rejection, got %+v %v", result, err)
	}
	if store.stock[1] != 5 || store.stock[2] != 1 {
		t.Fatalf("stock must be untouched, got %+v", store.stock)
	}
	if len(result.Shortages) != 1 || result.Shortages[0].ProductID != 2 || result.Shortages[0].Reason != domain.ReasonInsufficientStock || result.Shortages[0].Available != 1 {
		t.Fatalf("unexpected shortages %+v", result.Shortages)
	}
}

func TestReserveReportsUnknownProduct(t *testing.T) {
	svc, _ := newService(map[int64]int64{1: 5})
	result, err := svc.Reserve(context.Background(), "order-1", []domain.Item{{ProductID: 9, Quantity: 1}})
	if err != nil || result.Reserved || len(result.Shortages) != 1 || result.Shortages[0].Reason != domain.ReasonProductNotFound {
		t.Fatalf("unexpected %+v %v", result, err)
	}
}

func TestReserveIsIdempotent(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 5})
	items := []domain.Item{{ProductID: 1, Quantity: 2}}
	for i := 0; i < 3; i++ {
		result, err := svc.Reserve(context.Background(), "order-1", items)
		if err != nil || !result.Reserved {
			t.Fatalf("attempt %d failed: %+v %v", i, result, err)
		}
	}
	if store.stock[1] != 3 {
		t.Fatalf("stock must be decremented once, got %d", store.stock[1])
	}
}

func TestReserveRetriesOnDuplicateInsert(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 5})
	store.failInsert = domain.ErrDuplicate
	result, err := svc.Reserve(context.Background(), "order-1", []domain.Item{{ProductID: 1, Quantity: 1}})
	if !errors.Is(err, domain.ErrDuplicate) {
		t.Fatalf("expected duplicate error after retry, got %+v %v", result, err)
	}
	if store.stock[1] != 5 {
		t.Fatalf("failed reservation must roll back, got %d", store.stock[1])
	}
}

func TestReleaseRestoresStockOnce(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 5})
	ctx := context.Background()
	if _, err := svc.Reserve(ctx, "order-1", []domain.Item{{ProductID: 1, Quantity: 4}}); err != nil {
		t.Fatal(err)
	}
	first, err := svc.Release(ctx, "order-1")
	if err != nil || !first.Released || first.ItemsReleased != 1 {
		t.Fatalf("unexpected %+v %v", first, err)
	}
	second, _ := svc.Release(ctx, "order-1")
	if second.Released || store.stock[1] != 5 {
		t.Fatalf("second release must be a no-op, stock=%d", store.stock[1])
	}
}

func TestReserveAfterReleaseReportsNotReserved(t *testing.T) {
	svc, _ := newService(map[int64]int64{1: 5})
	ctx := context.Background()
	items := []domain.Item{{ProductID: 1, Quantity: 1}}
	_, _ = svc.Reserve(ctx, "order-1", items)
	_, _ = svc.Release(ctx, "order-1")
	result, err := svc.Reserve(ctx, "order-1", items)
	if err != nil || result.Reserved {
		t.Fatalf("released reservation must not replay as reserved: %+v %v", result, err)
	}
}

func TestValidation(t *testing.T) {
	svc, _ := newService(nil)
	ctx := context.Background()
	cases := map[string]func() error{
		"product id":     func() error { _, _, err := svc.GetStock(ctx, 0); return err },
		"negative stock": func() error { _, err := svc.SetStock(ctx, 1, -1); return err },
		"empty id":       func() error { _, err := svc.Reserve(ctx, "  ", []domain.Item{{ProductID: 1, Quantity: 1}}); return err },
		"long id": func() error {
			_, err := svc.Reserve(ctx, string(make([]byte, 65)), []domain.Item{{ProductID: 1, Quantity: 1}})
			return err
		},
		"no items": func() error { _, err := svc.Reserve(ctx, "a", nil); return err },
		"zero qty": func() error { _, err := svc.Reserve(ctx, "a", []domain.Item{{ProductID: 1, Quantity: 0}}); return err },
		"release":  func() error { _, err := svc.Release(ctx, ""); return err },
	}
	for name, run := range cases {
		if err := run(); !domain.IsValidation(err) {
			t.Errorf("%s: expected validation error, got %v", name, err)
		}
	}
}

func TestConcurrentReservationsNeverOversell(t *testing.T) {
	svc, store := newService(map[int64]int64{1: 1})
	var wg sync.WaitGroup
	var mu sync.Mutex
	succeeded := 0
	for i := 0; i < 50; i++ {
		wg.Add(1)
		go func(n int) {
			defer wg.Done()
			result, err := svc.Reserve(context.Background(), "order-"+string(rune('a'+n%26))+string(rune('A'+n/26)), []domain.Item{{ProductID: 1, Quantity: 1}})
			if err == nil && result.Reserved {
				mu.Lock()
				succeeded++
				mu.Unlock()
			}
		}(i)
	}
	wg.Wait()
	if succeeded != 1 || store.stock[1] != 0 {
		t.Fatalf("expected exactly one success and zero stock, got %d and %d", succeeded, store.stock[1])
	}
}
