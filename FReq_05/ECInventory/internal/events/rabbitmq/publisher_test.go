package rabbitmq_test

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"log/slog"
	"sync"
	"testing"
	"time"

	"ecinventory/internal/events"
	"ecinventory/internal/events/rabbitmq"
)

type fakeBroker struct {
	mu            sync.Mutex
	failOpens     int
	failPublishes int
	opens         int
	delivered     []events.Event
}

func (b *fakeBroker) Open(context.Context) (rabbitmq.Session, error) {
	b.mu.Lock()
	defer b.mu.Unlock()
	b.opens++
	if b.failOpens > 0 {
		b.failOpens--
		return nil, errors.New("broker down")
	}
	return &fakeSession{broker: b}, nil
}

func (b *fakeBroker) snapshot() []events.Event {
	b.mu.Lock()
	defer b.mu.Unlock()
	return append([]events.Event(nil), b.delivered...)
}

type fakeSession struct{ broker *fakeBroker }

func (s *fakeSession) Publish(_ context.Context, body []byte, _ string) error {
	s.broker.mu.Lock()
	defer s.broker.mu.Unlock()
	if s.broker.failPublishes > 0 {
		s.broker.failPublishes--
		return errors.New("not confirmed")
	}
	var event events.Event
	if err := json.Unmarshal(body, &event); err != nil {
		return err
	}
	s.broker.delivered = append(s.broker.delivered, event)
	return nil
}

func (s *fakeSession) Close() {}

func quiet() *slog.Logger { return slog.New(slog.NewTextHandler(io.Discard, nil)) }

func newPublisher(broker rabbitmq.Broker, buffer int) *rabbitmq.Publisher {
	return rabbitmq.NewPublisher(broker, rabbitmq.Options{
		Buffer:         buffer,
		InitialBackoff: time.Millisecond,
		MaxBackoff:     5 * time.Millisecond,
		Logger:         quiet(),
	})
}

func closeWithin(t *testing.T, publisher *rabbitmq.Publisher) {
	t.Helper()
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := publisher.Close(ctx); err != nil {
		t.Fatalf("close failed: %v", err)
	}
}

func event(id string, productID, stock int64) events.Event {
	return events.Event{ID: id, Type: events.TypeStockChanged, ProductID: productID, Stock: stock, Reason: events.ReasonSet}
}

func TestEventsAreDeliveredInOrder(t *testing.T) {
	broker := &fakeBroker{}
	publisher := newPublisher(broker, 16)

	for i := int64(1); i <= 10; i++ {
		publisher.Publish(context.Background(), event(string(rune('a'+i)), 1, i))
	}
	closeWithin(t, publisher)

	delivered := broker.snapshot()
	if len(delivered) != 10 {
		t.Fatalf("expected 10 events, got %d", len(delivered))
	}
	for i, got := range delivered {
		if got.Stock != int64(i+1) {
			t.Fatalf("event %d out of order: %+v", i, got)
		}
	}
}

func TestEventsSurviveABrokerOutage(t *testing.T) {
	broker := &fakeBroker{failOpens: 5}
	publisher := newPublisher(broker, 16)

	publisher.Publish(context.Background(), event("one", 1, 9))
	publisher.Publish(context.Background(), event("two", 1, 8))
	closeWithin(t, publisher)

	delivered := broker.snapshot()
	if len(delivered) != 2 || delivered[0].ID != "one" || delivered[1].ID != "two" {
		t.Fatalf("events were lost or reordered: %+v", delivered)
	}
	if broker.opens < 6 {
		t.Fatalf("expected the publisher to retry the connection, opens=%d", broker.opens)
	}
}

func TestAnUnconfirmedPublishIsRetriedOnANewSession(t *testing.T) {
	broker := &fakeBroker{failPublishes: 2}
	publisher := newPublisher(broker, 16)

	publisher.Publish(context.Background(), event("only", 4, 3))
	closeWithin(t, publisher)

	delivered := broker.snapshot()
	if len(delivered) != 1 || delivered[0].ID != "only" {
		t.Fatalf("expected exactly one delivery, got %+v", delivered)
	}
	if broker.opens != 3 {
		t.Fatalf("expected a new session per failed publish, opens=%d", broker.opens)
	}
}

func TestAFullBufferDropsInsteadOfBlocking(t *testing.T) {
	broker := &fakeBroker{failOpens: 1 << 30}
	publisher := newPublisher(broker, 2)

	done := make(chan struct{})
	go func() {
		for i := 0; i < 50; i++ {
			publisher.Publish(context.Background(), event("x", 1, int64(i)))
		}
		close(done)
	}()

	select {
	case <-done:
	case <-time.After(2 * time.Second):
		t.Fatal("Publish blocked while the broker was down")
	}

	ctx, cancel := context.WithTimeout(context.Background(), 50*time.Millisecond)
	defer cancel()
	if err := publisher.Close(ctx); err == nil {
		t.Fatal("expected a forced shutdown error while the broker is down")
	}
}

func TestPublishAfterCloseIsIgnored(t *testing.T) {
	broker := &fakeBroker{}
	publisher := newPublisher(broker, 4)
	closeWithin(t, publisher)

	publisher.Publish(context.Background(), event("late", 1, 1))

	if len(broker.snapshot()) != 0 {
		t.Fatal("an event was published after close")
	}
	closeWithin(t, publisher)
}
