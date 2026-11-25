package broker

import (
	"context"
	"errors"
	"fmt"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"

	"ecinventory/internal/events/rabbitmq"
)

type Config struct {
	Host                 string
	Port                 int
	User                 string
	Password             string
	VHost                string
	Exchange             string
	RoutingKey           string
	Queue                string
	DeadLetterExchange   string
	DeadLetterRoutingKey string
	DeadLetterQueue      string
	DialTimeout          time.Duration
	ConfirmTimeout       time.Duration
}

type AMQP struct {
	config Config
}

func New(config Config) *AMQP {
	if config.DialTimeout <= 0 {
		config.DialTimeout = 5 * time.Second
	}
	if config.ConfirmTimeout <= 0 {
		config.ConfirmTimeout = 5 * time.Second
	}
	return &AMQP{config: config}
}

func (b *AMQP) Open(_ context.Context) (rabbitmq.Session, error) {
	uri := amqp.URI{
		Scheme:   "amqp",
		Host:     b.config.Host,
		Port:     b.config.Port,
		Username: b.config.User,
		Password: b.config.Password,
		Vhost:    b.config.VHost,
	}

	connection, err := amqp.DialConfig(uri.String(), amqp.Config{
		Dial:      amqp.DefaultDial(b.config.DialTimeout),
		Heartbeat: 10 * time.Second,
		Locale:    "en_US",
	})
	if err != nil {
		return nil, fmt.Errorf("dial rabbitmq: %w", err)
	}

	channel, err := connection.Channel()
	if err != nil {
		_ = connection.Close()
		return nil, fmt.Errorf("open channel: %w", err)
	}

	if err := b.declare(channel); err != nil {
		_ = connection.Close()
		return nil, err
	}

	if err := channel.Confirm(false); err != nil {
		_ = connection.Close()
		return nil, fmt.Errorf("enable publisher confirms: %w", err)
	}

	return &amqpSession{connection: connection, channel: channel, config: b.config}, nil
}

func (b *AMQP) declare(channel *amqp.Channel) error {
	c := b.config

	if err := channel.ExchangeDeclare(c.Exchange, "topic", true, false, false, false, nil); err != nil {
		return fmt.Errorf("declare exchange: %w", err)
	}
	if err := channel.ExchangeDeclare(c.DeadLetterExchange, "direct", true, false, false, false, nil); err != nil {
		return fmt.Errorf("declare dead letter exchange: %w", err)
	}
	if _, err := channel.QueueDeclare(c.DeadLetterQueue, true, false, false, false, nil); err != nil {
		return fmt.Errorf("declare dead letter queue: %w", err)
	}
	if err := channel.QueueBind(c.DeadLetterQueue, c.DeadLetterRoutingKey, c.DeadLetterExchange, false, nil); err != nil {
		return fmt.Errorf("bind dead letter queue: %w", err)
	}

	arguments := amqp.Table{
		"x-dead-letter-exchange":    c.DeadLetterExchange,
		"x-dead-letter-routing-key": c.DeadLetterRoutingKey,
	}
	if _, err := channel.QueueDeclare(c.Queue, true, false, false, false, arguments); err != nil {
		return fmt.Errorf("declare queue: %w", err)
	}
	if err := channel.QueueBind(c.Queue, c.RoutingKey, c.Exchange, false, nil); err != nil {
		return fmt.Errorf("bind queue: %w", err)
	}
	return nil
}

type amqpSession struct {
	connection *amqp.Connection
	channel    *amqp.Channel
	config     Config
}

func (s *amqpSession) Publish(ctx context.Context, body []byte, messageID string) error {
	if s.connection.IsClosed() {
		return errors.New("rabbitmq connection is closed")
	}

	ctx, cancel := context.WithTimeout(ctx, s.config.ConfirmTimeout)
	defer cancel()

	confirmation, err := s.channel.PublishWithDeferredConfirmWithContext(ctx, s.config.Exchange, s.config.RoutingKey, false, false, amqp.Publishing{
		ContentType:  "application/json",
		DeliveryMode: amqp.Persistent,
		MessageId:    messageID,
		Timestamp:    time.Now().UTC(),
		Type:         "stock.changed",
		Body:         body,
	})
	if err != nil {
		return fmt.Errorf("publish: %w", err)
	}

	acknowledged, err := confirmation.WaitContext(ctx)
	if err != nil {
		return fmt.Errorf("wait for confirmation: %w", err)
	}
	if !acknowledged {
		return errors.New("rabbitmq rejected the message")
	}
	return nil
}

func (s *amqpSession) Close() {
	_ = s.channel.Close()
	_ = s.connection.Close()
}
