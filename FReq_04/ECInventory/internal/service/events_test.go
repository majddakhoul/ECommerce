package service_test

import (
	"context"
	"sync"
	"testing"
	"time"

	"ecinventory/internal/domain"
	"ecinventory/internal/events"
	"ecinventory/internal/service"
)

type recorder struct {
	mu     sync.Mutex
	events []events.Event
}

func (r *recorder) Publish(_ context.Context, event events.Event) {
	r.mu.Lock()
	defer r.mu.Unlock()
	r.events = append(r.events, event)
}

func (r *recorder) Close(context.Context) error { return nil }

func (r *recorder) all() []events.Event {
	r.mu.Lock()
	defer r.mu.Unlock()
	return append([]events.Event(nil), r.events...)
}

func publishingService(stock map[int64]int64) (*service.Inventory, *recorder, context.Context) {
	store := newMemoryStore()
	for id, value := range stock {
		store.stock[id] = value
	}
	rec := &recorder{}
	fixed := time.Date(2026, 11, 16, 12, 0, 0, 0, time.UTC)
	svc := service.NewInventory(store, service.WithPublisher(rec), service.WithClock(func() time.Time { return fixed }))
	return svc, rec, events.WithPublishing(context.Background())
}

func TestNothingIsPublishedWithoutThePublishingFlag(t *testing.T) {
	svc, rec, _ := publishingService(map[int64]int64{1: 10})
	ctx := context.Background()

	if _, err := svc.SetStock(ctx, 1, 4); err != nil {
		t.Fatal(err)
	}
	if _, err := svc.Reserve(ctx, "r-1", []domain.Item{{ProductID: 1, Quantity: 1}}); err != nil {
		t.Fatal(err)
	}
	if _, err := svc.Release(ctx, "r-1"); err != nil {
		t.Fatal(err)
	}
	if _, err := svc.DeleteStock(ctx, 1); err != nil {
		t.Fatal(err)
	}

	if got := rec.all(); len(got) != 0 {
		t.Fatalf("sync traffic must not publish, got %+v", got)
	}
}

func TestSetStockPublishesTheNewQuantity(t *testing.T) {
	svc, rec, ctx := publishingService(nil)

	if _, err := svc.SetStock(ctx, 7, 12); err != nil {
		t.Fatal(err)
	}

	got := rec.all()
	if len(got) != 1 || got[0].ProductID != 7 || got[0].Stock != 12 || got[0].Reason != events.ReasonSet || got[0].Deleted {
		t.Fatalf("unexpected events %+v", got)
	}
	if got[0].Type != events.TypeStockChanged || got[0].ID == "" || got[0].Version == 0 {
		t.Fatalf("event metadata is incomplete %+v", got[0])
	}
}

func TestInvalidSetStockPublishesNothing(t *testing.T) {
	svc, rec, ctx := publishingService(nil)

	if _, err := svc.SetStock(ctx, 0, 5); err == nil {
		t.Fatal("expected a validation error")
	}
	if got := rec.all(); len(got) != 0 {
		t.Fatalf("unexpected events %+v", got)
	}
}

func TestDeleteStockPublishesOnlyWhenARowWasRemoved(t *testing.T) {
	svc, rec, ctx := publishingService(map[int64]int64{3: 5})

	if _, err := svc.DeleteStock(ctx, 99); err != nil {
		t.Fatal(err)
	}
	if got := rec.all(); len(got) != 0 {
		t.Fatalf("deleting an unknown product must not publish, got %+v", got)
	}

	if _, err := svc.DeleteStock(ctx, 3); err != nil {
		t.Fatal(err)
	}
	got := rec.all()
	if len(got) != 1 || !got[0].Deleted || got[0].Stock != 0 || got[0].Reason != events.ReasonDeleted {
		t.Fatalf("unexpected events %+v", got)
	}
}

func TestReservePublishesTheRemainingStockOfEveryItem(t *testing.T) {
	svc, rec, ctx := publishingService(map[int64]int64{1: 10, 2: 5})

	result, err := svc.Reserve(ctx, "order-1", []domain.Item{{ProductID: 1, Quantity: 3}, {ProductID: 2, Quantity: 5}})
	if err != nil || !result.Reserved {
		t.Fatalf("reservation failed: %+v %v", result, err)
	}

	byProduct := map[int64]events.Event{}
	for _, event := range rec.all() {
		byProduct[event.ProductID] = event
	}
	if len(byProduct) != 2 || byProduct[1].Stock != 7 || byProduct[2].Stock != 0 {
		t.Fatalf("unexpected events %+v", byProduct)
	}
	if byProduct[1].Reason != events.ReasonReserved {
		t.Fatalf("unexpected reason %q", byProduct[1].Reason)
	}
}

func TestARejectedReservationPublishesNothing(t *testing.T) {
	svc, rec, ctx := publishingService(map[int64]int64{1: 2})

	result, err := svc.Reserve(ctx, "order-2", []domain.Item{{ProductID: 1, Quantity: 3}})
	if err != nil || result.Reserved {
		t.Fatalf("expected a rejection, got %+v %v", result, err)
	}
	if got := rec.all(); len(got) != 0 {
		t.Fatalf("unexpected events %+v", got)
	}
}

func TestReleasePublishesTheRestoredStockOnce(t *testing.T) {
	svc, rec, ctx := publishingService(map[int64]int64{1: 10})

	if _, err := svc.Reserve(ctx, "order-3", []domain.Item{{ProductID: 1, Quantity: 4}}); err != nil {
		t.Fatal(err)
	}
	before := len(rec.all())

	result, err := svc.Release(ctx, "order-3")
	if err != nil || !result.Released {
		t.Fatalf("release failed: %+v %v", result, err)
	}

	got := rec.all()[before:]
	if len(got) != 1 || got[0].Stock != 10 || got[0].Reason != events.ReasonReleased {
		t.Fatalf("unexpected events %+v", got)
	}

	if _, err := svc.Release(ctx, "order-3"); err != nil {
		t.Fatal(err)
	}
	if again := rec.all()[before:]; len(again) != 1 {
		t.Fatalf("a repeated release must not publish again, got %+v", again)
	}
}

func TestVersionsGrowWithTime(t *testing.T) {
	store := newMemoryStore()
	rec := &recorder{}
	tick := time.Date(2026, 11, 16, 12, 0, 0, 0, time.UTC)
	svc := service.NewInventory(store, service.WithPublisher(rec), service.WithClock(func() time.Time {
		tick = tick.Add(time.Millisecond)
		return tick
	}))
	ctx := events.WithPublishing(context.Background())

	for stock := int64(1); stock <= 3; stock++ {
		if _, err := svc.SetStock(ctx, 1, stock); err != nil {
			t.Fatal(err)
		}
	}

	got := rec.all()
	if len(got) != 3 || !(got[0].Version < got[1].Version && got[1].Version < got[2].Version) {
		t.Fatalf("versions must be strictly increasing, got %+v", got)
	}
}
