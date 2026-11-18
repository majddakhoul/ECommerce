package main

import (
	"context"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"ecinventory/internal/config"
	"ecinventory/internal/seed"
	"ecinventory/internal/store/mysql"
)

func main() {
	if err := run(); err != nil {
		fmt.Fprintln(os.Stderr, "seed failed:", err)
		os.Exit(1)
	}
}

func run() error {
	min := flag.Int64("min", 10, "minimum stock per product")
	max := flag.Int64("max", 200, "maximum stock per product")
	randomSeed := flag.Int64("seed", 1, "random seed, the same value gives the same quantities")
	flag.Parse()

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

	source, err := mysql.OpenProductSource(cfg)
	if err != nil {
		return err
	}
	defer source.Close()

	result, err := seed.Run(ctx, source, store, seed.Options{Min: *min, Max: *max, Seed: *randomSeed})
	if err != nil {
		return err
	}
	fmt.Printf("inventory reset and seeded: %d products, %d units\n", result.Products, result.Units)
	return nil
}
