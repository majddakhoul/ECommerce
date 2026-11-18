package mysql

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"log/slog"
	"net"
	"time"

	driver "github.com/go-sql-driver/mysql"

	"ecinventory/internal/config"
	"ecinventory/internal/domain"
	"ecinventory/internal/service"
)

const (
	errDuplicateEntry = 1062
	errDeadlock       = 1213
	errLockTimeout    = 1205
	deadlockRetries   = 3
)

type Store struct {
	db *sql.DB
}

func Open(cfg config.Config) (*Store, error) {
	db, err := connect(cfg, cfg.DBHost, cfg.DBPort, cfg.DBUser, cfg.DBPassword, cfg.DBName)
	if err != nil {
		return nil, err
	}
	return &Store{db: db}, nil
}

func connect(cfg config.Config, host string, port int, user, password, name string) (*sql.DB, error) {
	dsn := driver.NewConfig()
	dsn.User = user
	dsn.Passwd = password
	dsn.Net = "tcp"
	dsn.Addr = net.JoinHostPort(host, fmt.Sprint(port))
	dsn.DBName = name
	dsn.ParseTime = true
	dsn.ClientFoundRows = true
	dsn.Timeout = 5 * time.Second

	db, err := sql.Open("mysql", dsn.FormatDSN())
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(cfg.MaxOpenConns)
	db.SetMaxIdleConns(cfg.MaxIdleConns)
	db.SetConnMaxLifetime(cfg.ConnMaxLifetime)
	return db, nil
}

func (s *Store) Close() error { return s.db.Close() }

func (s *Store) Migrate(ctx context.Context, attempts int, delay time.Duration) error {
	var lastErr error
	for attempt := 1; attempt <= attempts; attempt++ {
		lastErr = s.applySchema(ctx)
		if lastErr == nil {
			slog.Info("database schema is ready")
			return nil
		}
		slog.Warn("database is not ready yet", "attempt", attempt, "error", lastErr)
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(delay):
		}
	}
	return lastErr
}

func (s *Store) applySchema(ctx context.Context) error {
	for _, statement := range statements {
		if _, err := s.db.ExecContext(ctx, statement); err != nil {
			return err
		}
	}
	return nil
}

func (s *Store) Ping(ctx context.Context) error {
	return translate(s.db.PingContext(ctx))
}

func (s *Store) Repositories() service.Repositories {
	return service.Repositories{
		Inventory:    &inventoryRepository{q: s.db},
		Reservations: &reservationRepository{q: s.db},
	}
}

func (s *Store) InTransaction(ctx context.Context, work func(service.Repositories) error) error {
	var err error
	for attempt := 0; attempt < deadlockRetries; attempt++ {
		err = s.runTransaction(ctx, work)
		if !isRetryable(err) {
			return err
		}
	}
	return err
}

func (s *Store) runTransaction(ctx context.Context, work func(service.Repositories) error) error {
	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return translate(err)
	}
	repos := service.Repositories{
		Inventory:    &inventoryRepository{q: tx},
		Reservations: &reservationRepository{q: tx},
	}
	if err := work(repos); err != nil {
		_ = tx.Rollback()
		return err
	}
	return translate(tx.Commit())
}

type queryer interface {
	ExecContext(ctx context.Context, query string, args ...any) (sql.Result, error)
	QueryContext(ctx context.Context, query string, args ...any) (*sql.Rows, error)
	QueryRowContext(ctx context.Context, query string, args ...any) *sql.Row
}

func mysqlNumber(err error) uint16 {
	var mysqlErr *driver.MySQLError
	if errors.As(err, &mysqlErr) {
		return mysqlErr.Number
	}
	return 0
}

func isRetryable(err error) bool {
	number := mysqlNumber(err)
	return number == errDeadlock || number == errLockTimeout
}

func translate(err error) error {
	if err == nil {
		return nil
	}
	if mysqlNumber(err) == errDuplicateEntry {
		return domain.ErrDuplicate
	}
	var netErr net.Error
	if errors.As(err, &netErr) || errors.Is(err, driver.ErrInvalidConn) || errors.Is(err, sql.ErrConnDone) {
		return fmt.Errorf("%w: %v", domain.ErrUnavailable, err)
	}
	return err
}
