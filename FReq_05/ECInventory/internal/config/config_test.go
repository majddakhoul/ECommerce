package config_test

import (
	"testing"

	"ecinventory/internal/config"
)

func TestDefaults(t *testing.T) {
	t.Setenv("GRPC_PORT", "")
	cfg := config.Load()
	if cfg.GRPCAddress != "0.0.0.0:50051" || cfg.MaxOpenConns != 25 {
		t.Fatalf("unexpected defaults %+v", cfg)
	}
}

func TestOverrides(t *testing.T) {
	t.Setenv("GRPC_PORT", "6000")
	t.Setenv("DB_MAX_OPEN_CONNS", "7")
	cfg := config.Load()
	if cfg.GRPCAddress != "0.0.0.0:6000" || cfg.MaxOpenConns != 7 {
		t.Fatalf("unexpected config %+v", cfg)
	}
}
