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

	server := grpcapi.New(service.NewInventory(store), cfg.APIKey)
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
