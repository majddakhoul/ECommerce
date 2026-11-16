package config

import (
	"os"
	"strconv"
	"time"
)

type Config struct {
	GRPCAddress     string
	APIKey          string
	DBHost          string
	DBPort          int
	DBUser          string
	DBPassword      string
	DBName          string
	AppDBHost       string
	AppDBPort       int
	AppDBUser       string
	AppDBPassword   string
	AppDBName       string
	MaxOpenConns    int
	MaxIdleConns    int
	ConnMaxLifetime time.Duration
	MigrateAttempts int
	MigrateDelay    time.Duration
	ShutdownTimeout time.Duration
}

func Load() Config {
	return Config{
		GRPCAddress:     env("GRPC_HOST", "0.0.0.0") + ":" + env("GRPC_PORT", "50051"),
		APIKey:          env("INVENTORY_API_KEY", ""),
		DBHost:          env("DB_HOST", "127.0.0.1"),
		DBPort:          envInt("DB_PORT", 3306),
		DBUser:          env("DB_USER", "inventory"),
		DBPassword:      env("DB_PASSWORD", ""),
		DBName:          env("DB_NAME", "inventory"),
		AppDBHost:       env("APP_DB_HOST", "127.0.0.1"),
		AppDBPort:       envInt("APP_DB_PORT", 3306),
		AppDBUser:       env("APP_DB_USER", "root"),
		AppDBPassword:   env("APP_DB_PASSWORD", ""),
		AppDBName:       env("APP_DB_NAME", "auth_system"),
		MaxOpenConns:    envInt("DB_MAX_OPEN_CONNS", 25),
		MaxIdleConns:    envInt("DB_MAX_IDLE_CONNS", 10),
		ConnMaxLifetime: time.Duration(envInt("DB_CONN_MAX_LIFETIME_SECONDS", 300)) * time.Second,
		MigrateAttempts: envInt("DB_MIGRATE_ATTEMPTS", 30),
		MigrateDelay:    time.Duration(envInt("DB_MIGRATE_DELAY_SECONDS", 2)) * time.Second,
		ShutdownTimeout: time.Duration(envInt("SHUTDOWN_TIMEOUT_SECONDS", 10)) * time.Second,
	}
}

func env(key, fallback string) string {
	if value, ok := os.LookupEnv(key); ok && value != "" {
		return value
	}
	return fallback
}

func envInt(key string, fallback int) int {
	value, err := strconv.Atoi(os.Getenv(key))
	if err != nil {
		return fallback
	}
	return value
}
