//go:build integration

package integration

import (
	"context"
	"fmt"
	"sync"
	"testing"
	"time"

	"ecinventory/internal/config"
	"ecinventory/internal/domain"
	"ecinventory/internal/service"
	"ecinventory/internal/store/mysql"
)

func newService(t *testing.T) *service.Inventory {
	t.Helper()
	store, err := mysql.Open(config.Load())
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = store.Close() })
	if err := store.Migrate(context.Background(), 15, 2*time.Second); err != nil {
		t.Fatal(err)
	}
	return service.NewInventory(store)
}

func uniqueProduct() int64 {
	return time.Now().UnixNano() % 1_000_000_000
}

func TestConcurrentReservationsOnMySQL(t *testing.T) {
	svc := newService(t)
	ctx := context.Background()
	productID := uniqueProduct()
	if _, err := svc.SetStock(ctx, productID, 1); err != nil {
		t.Fatal(err)
	}

	const attempts = 10
	var wg sync.WaitGroup
	var mu sync.Mutex
	reserved, rejected := 0, 0
	for i := 0; i < attempts; i++ {
		wg.Add(1)
		go func(n int) {
			defer wg.Done()
			result, err := svc.Reserve(ctx, fmt.Sprintf("race-%d-%d", productID, n), []domain.Item{{ProductID: productID, Quantity: 1}})
			if err != nil {
				t.Errorf("reserve failed: %v", err)
				return
			}
			mu.Lock()
			defer mu.Unlock()
			if result.Reserved {
				reserved++
			} else {
				rejected++
			}
		}(i)
	}
	wg.Wait()

	if reserved != 1 || rejected != attempts-1 {
		t.Fatalf("expected 1 reserved and %d rejected, got %d and %d", attempts-1, reserved, rejected)
	}
	stock, _, _ := svc.GetStock(ctx, productID)
	if stock != 0 {
		t.Fatalf("stock must be zero, got %d", stock)
	}
}

func TestReleaseRestoresStockOnMySQL(t *testing.T) {
	svc := newService(t)
	ctx := context.Background()
	productID := uniqueProduct()
	reservation := fmt.Sprintf("release-%d", productID)
	_, _ = svc.SetStock(ctx, productID, 5)

	if result, err := svc.Reserve(ctx, reservation, []domain.Item{{ProductID: productID, Quantity: 3}}); err != nil || !result.Reserved {
		t.Fatalf("reserve failed: %+v %v", result, err)
	}
	if _, err := svc.Release(ctx, reservation); err != nil {
		t.Fatal(err)
	}
	if _, err := svc.Release(ctx, reservation); err != nil {
		t.Fatal(err)
	}
	stock, _, _ := svc.GetStock(ctx, productID)
	if stock != 5 {
		t.Fatalf("stock must be restored exactly once, got %d", stock)
	}
}

func TestSameReservationConcurrentlyDecrementsOnce(t *testing.T) {
	svc := newService(t)
	ctx := context.Background()
	productID := uniqueProduct()
	reservation := fmt.Sprintf("same-%d", productID)
	_, _ = svc.SetStock(ctx, productID, 10)

	var wg sync.WaitGroup
	for i := 0; i < 8; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			if _, err := svc.Reserve(ctx, reservation, []domain.Item{{ProductID: productID, Quantity: 2}}); err != nil {
				t.Errorf("reserve failed: %v", err)
			}
		}()
	}
	wg.Wait()

	stock, _, _ := svc.GetStock(ctx, productID)
	if stock != 8 {
		t.Fatalf("expected a single decrement leaving 8, got %d", stock)
	}
}
