package main

import (
	"context"
	"log/slog"
	"net"
	"os"
	"os/signal"
	"syscall"
	"time"

	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"

	"ecinventory/gen/inventorypb"
	"ecinventory/internal/config"
	"ecinventory/internal/events"
	"ecinventory/internal/events/rabbitmq"
	"ecinventory/internal/events/rabbitmq/broker"
	"ecinventory/internal/service"
	"ecinventory/internal/store/mysql"
	"ecinventory/internal/transport/grpcapi"
)

func main() {
	slog.SetDefault(slog.New(slog.NewJSONHandler(os.Stdout, nil)))

	if len(os.Args) > 1 && os.Args[1] == "healthcheck" {
		os.Exit(healthcheck())
	}

	if err := run(); err != nil {
		slog.Error("inventory service failed", "error", err)
		os.Exit(1)
	}
}

func run() error {
	cfg := config.Load()

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	store, err := mysql.Open(cfg)
	if err != nil {
		return err
	}
	defer store.Close()

	if err := store.Migrate(ctx, cfg.MigrateAttempts, cfg.MigrateDelay); err != nil {
		return err
	}

	listener, err := net.Listen("tcp", cfg.GRPCAddress)
	if err != nil {
		return err
	}

	publisher := newPublisher(cfg)
	defer closePublisher(publisher, cfg.ShutdownTimeout)

	server := grpcapi.New(service.NewInventory(store, service.WithPublisher(publisher)), cfg.APIKey)
	slog.Info("inventory gRPC server is listening", "address", cfg.GRPCAddress)

	serveErr := make(chan error, 1)
	go func() { serveErr <- server.Serve(listener) }()

	select {
	case err := <-serveErr:
		return err
	case <-ctx.Done():
	}

	slog.Info("shutting down")
	done := make(chan struct{})
	go func() {
		server.GracefulStop()
		close(done)
	}()
	select {
	case <-done:
	case <-time.After(cfg.ShutdownTimeout):
		server.Stop()
	}
	return nil
}

func healthcheck() int {
	cfg := config.Load()
	_, port, err := net.SplitHostPort(cfg.GRPCAddress)
	if err != nil {
		return 1
	}
	conn, err := grpc.NewClient("127.0.0.1:"+port, grpc.WithTransportCredentials(insecure.NewCredentials()))
	if err != nil {
		return 1
	}
	defer conn.Close()

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	response, err := inventorypb.NewInventoryServiceClient(conn).Ping(ctx, &inventorypb.PingRequest{})
	if err != nil || !response.GetOk() {
		return 1
	}
	return 0
}

func newPublisher(cfg config.Config) events.Publisher {
	if cfg.RabbitHost == "" {
		slog.Info("rabbitmq is not configured, stock events are disabled")
		return events.Noop{}
	}

	slog.Info("publishing stock events to rabbitmq", "host", cfg.RabbitHost, "exchange", cfg.EventsExchange)

	amqpBroker := broker.New(broker.Config{
		Host:                 cfg.RabbitHost,
		Port:                 cfg.RabbitPort,
		User:                 cfg.RabbitUser,
		Password:             cfg.RabbitPassword,
		VHost:                cfg.RabbitVHost,
		Exchange:             cfg.EventsExchange,
		RoutingKey:           cfg.StockRoutingKey,
		Queue:                cfg.StockQueue,
		DeadLetterExchange:   cfg.StockDeadExchange,
		DeadLetterRoutingKey: cfg.StockDeadRoutingKey,
		DeadLetterQueue:      cfg.StockDeadQueue,
	})
	return rabbitmq.NewPublisher(amqpBroker, rabbitmq.Options{Buffer: cfg.EventBuffer})
}

func closePublisher(publisher events.Publisher, timeout time.Duration) {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()
	if err := publisher.Close(ctx); err != nil {
		slog.Warn("stock events were not fully published before shutdown", "error", err)
	}
}
