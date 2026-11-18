package rabbitmq

import (
	"context"
	"encoding/json"
	"log/slog"
	"sync"
	"time"

	"ecinventory/internal/events"
)

type Session interface {
	Publish(ctx context.Context, body []byte, messageID string) error
	Close()
}

type Broker interface {
	Open(ctx context.Context) (Session, error)
}

type Options struct {
	Buffer         int
	InitialBackoff time.Duration
	MaxBackoff     time.Duration
	Logger         *slog.Logger
}

type Publisher struct {
	broker  Broker
	options Options

	mu      sync.RWMutex
	closed  bool
	queue   chan events.Event
	abort   chan struct{}
	stopped chan struct{}
}

func NewPublisher(broker Broker, options Options) *Publisher {
	if options.Buffer <= 0 {
		options.Buffer = 1024
	}
	if options.InitialBackoff <= 0 {
		options.InitialBackoff = 500 * time.Millisecond
	}
	if options.MaxBackoff <= 0 {
		options.MaxBackoff = 15 * time.Second
	}
	if options.Logger == nil {
		options.Logger = slog.Default()
	}

	publisher := &Publisher{
		broker:  broker,
		options: options,
		queue:   make(chan events.Event, options.Buffer),
		abort:   make(chan struct{}),
		stopped: make(chan struct{}),
	}
	go publisher.run()
	return publisher
}

func (p *Publisher) Publish(_ context.Context, event events.Event) {
	p.mu.RLock()
	defer p.mu.RUnlock()

	if p.closed {
		p.options.Logger.Warn("event dropped because the publisher is closed", "event_id", event.ID)
		return
	}

	select {
	case p.queue <- event:
	default:
		p.options.Logger.Error("event dropped because the publish buffer is full", "event_id", event.ID, "product_id", event.ProductID)
	}
}

func (p *Publisher) Close(ctx context.Context) error {
	p.mu.Lock()
	if !p.closed {
		p.closed = true
		close(p.queue)
	}
	p.mu.Unlock()

	select {
	case <-p.stopped:
		return nil
	case <-ctx.Done():
		close(p.abort)
		<-p.stopped
		return ctx.Err()
	}
}

func (p *Publisher) run() {
	defer close(p.stopped)

	var session Session
	defer func() {
		if session != nil {
			session.Close()
		}
	}()

	for event := range p.queue {
		body, err := json.Marshal(event)
		if err != nil {
			p.options.Logger.Error("event could not be encoded", "event_id", event.ID, "error", err)
			continue
		}

		session = p.deliver(session, event, body)
	}
}

func (p *Publisher) deliver(session Session, event events.Event, body []byte) Session {
	backoff := p.options.InitialBackoff

	for {
		if p.aborted() {
			p.options.Logger.Error("event dropped because shutdown was forced", "event_id", event.ID)
			return session
		}

		if session == nil {
			opened, err := p.broker.Open(context.Background())
			if err != nil {
				p.options.Logger.Warn("rabbitmq is unavailable, will retry", "error", err, "retry_in", backoff.String())
				if !p.sleep(backoff) {
					continue
				}
				backoff = p.next(backoff)
				continue
			}
			session = opened
			backoff = p.options.InitialBackoff
		}

		if err := session.Publish(context.Background(), body, event.ID); err != nil {
			p.options.Logger.Warn("event was not confirmed, reconnecting", "event_id", event.ID, "error", err)
			session.Close()
			session = nil
			continue
		}

		return session
	}
}

func (p *Publisher) sleep(delay time.Duration) bool {
	timer := time.NewTimer(delay)
	defer timer.Stop()

	select {
	case <-timer.C:
		return true
	case <-p.abort:
		return false
	}
}

func (p *Publisher) aborted() bool {
	select {
	case <-p.abort:
		return true
	default:
		return false
	}
}

func (p *Publisher) next(current time.Duration) time.Duration {
	doubled := current * 2
	if doubled > p.options.MaxBackoff {
		return p.options.MaxBackoff
	}
	return doubled
}
