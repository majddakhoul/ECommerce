package events

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"time"
)

const TypeStockChanged = "stock.changed"

const (
	ReasonSet      = "set"
	ReasonDeleted  = "deleted"
	ReasonReserved = "reserved"
	ReasonReleased = "released"
)

type Event struct {
	ID         string    `json:"event_id"`
	Type       string    `json:"type"`
	ProductID  int64     `json:"product_id"`
	Stock      int64     `json:"stock"`
	Deleted    bool      `json:"deleted"`
	Reason     string    `json:"reason"`
	Version    int64     `json:"version"`
	OccurredAt time.Time `json:"occurred_at"`
}

type Publisher interface {
	Publish(ctx context.Context, event Event)
	Close(ctx context.Context) error
}

type Noop struct{}

func (Noop) Publish(context.Context, Event) {}

func (Noop) Close(context.Context) error { return nil }

func NewStockChanged(productID, stock int64, deleted bool, reason string, at time.Time) Event {
	return Event{
		ID:         NewID(),
		Type:       TypeStockChanged,
		ProductID:  productID,
		Stock:      stock,
		Deleted:    deleted,
		Reason:     reason,
		Version:    at.UnixMicro(),
		OccurredAt: at.UTC(),
	}
}

func NewID() string {
	raw := make([]byte, 16)
	if _, err := rand.Read(raw); err != nil {
		return hex.EncodeToString([]byte(time.Now().UTC().Format(time.RFC3339Nano)))
	}
	raw[6] = (raw[6] & 0x0f) | 0x40
	raw[8] = (raw[8] & 0x3f) | 0x80
	encoded := hex.EncodeToString(raw)
	return encoded[0:8] + "-" + encoded[8:12] + "-" + encoded[12:16] + "-" + encoded[16:20] + "-" + encoded[20:32]
}

type publishKey struct{}

func WithPublishing(ctx context.Context) context.Context {
	return context.WithValue(ctx, publishKey{}, true)
}

func Enabled(ctx context.Context) bool {
	enabled, _ := ctx.Value(publishKey{}).(bool)
	return enabled
}
