package events_test

import (
	"context"
	"encoding/json"
	"regexp"
	"testing"
	"time"

	"ecinventory/internal/events"
)

func TestStockChangedPayloadMatchesTheContractWithLaravel(t *testing.T) {
	at := time.Date(2026, 11, 16, 10, 30, 0, 123456000, time.UTC)
	event := events.NewStockChanged(12, 7, false, events.ReasonReserved, at)

	raw, err := json.Marshal(event)
	if err != nil {
		t.Fatal(err)
	}

	var decoded map[string]any
	if err := json.Unmarshal(raw, &decoded); err != nil {
		t.Fatal(err)
	}

	for _, key := range []string{"event_id", "type", "product_id", "stock", "deleted", "reason", "version", "occurred_at"} {
		if _, ok := decoded[key]; !ok {
			t.Fatalf("payload is missing %q: %s", key, raw)
		}
	}
	if decoded["type"] != "stock.changed" || decoded["reason"] != "reserved" {
		t.Fatalf("unexpected payload %s", raw)
	}
	if decoded["product_id"].(float64) != 12 || decoded["stock"].(float64) != 7 {
		t.Fatalf("unexpected payload %s", raw)
	}
	if int64(decoded["version"].(float64)) != at.UnixMicro() {
		t.Fatalf("version must be the occurrence time in microseconds, got %v", decoded["version"])
	}
}

func TestVersionIsSafeForJavaScriptNumbers(t *testing.T) {
	version := events.NewStockChanged(1, 1, false, events.ReasonSet, time.Now()).Version
	const maxSafeInteger = 1<<53 - 1
	if version <= 0 || version > maxSafeInteger {
		t.Fatalf("version %d does not fit a JavaScript number", version)
	}
}

func TestIDsAreUniqueUUIDs(t *testing.T) {
	pattern := regexp.MustCompile(`^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$`)
	seen := map[string]bool{}
	for i := 0; i < 1000; i++ {
		id := events.NewID()
		if !pattern.MatchString(id) {
			t.Fatalf("not a UUID v4: %s", id)
		}
		if seen[id] {
			t.Fatalf("duplicate id %s", id)
		}
		seen[id] = true
	}
}

func TestPublishingFlagTravelsWithTheContext(t *testing.T) {
	ctx := context.Background()
	if events.Enabled(ctx) {
		t.Fatal("publishing must be off by default")
	}
	if !events.Enabled(events.WithPublishing(ctx)) {
		t.Fatal("publishing must be on after WithPublishing")
	}
}

func TestNoopAcceptsEverything(t *testing.T) {
	var publisher events.Publisher = events.Noop{}
	publisher.Publish(context.Background(), events.Event{})
	if err := publisher.Close(context.Background()); err != nil {
		t.Fatal(err)
	}
}
