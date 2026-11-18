package seed

import (
	"context"
	"errors"
	"fmt"
	"math/rand"

	"ecinventory/internal/service"
)

var ErrNoProducts = errors.New("no products found in the application database")

type ProductSource interface {
	ProductIDs(ctx context.Context) ([]int64, error)
}

type Target interface {
	service.Store
	Reset(ctx context.Context) error
}

type Options struct {
	Min  int64
	Max  int64
	Seed int64
}

type Result struct {
	Products int
	Units    int64
}

func (o Options) validate() error {
	if o.Min < 0 {
		return fmt.Errorf("min must not be negative")
	}
	if o.Max < o.Min {
		return fmt.Errorf("max must not be lower than min")
	}
	return nil
}

func Run(ctx context.Context, source ProductSource, target Target, options Options) (Result, error) {
	if err := options.validate(); err != nil {
		return Result{}, err
	}
	ids, err := source.ProductIDs(ctx)
	if err != nil {
		return Result{}, err
	}
	if len(ids) == 0 {
		return Result{}, ErrNoProducts
	}
	if err := target.Reset(ctx); err != nil {
		return Result{}, err
	}

	generator := rand.New(rand.NewSource(options.Seed))
	span := options.Max - options.Min + 1
	result := Result{Products: len(ids)}

	err = target.InTransaction(ctx, func(repos service.Repositories) error {
		result.Units = 0
		for _, id := range ids {
			quantity := options.Min + generator.Int63n(span)
			if err := repos.Inventory.UpsertStock(ctx, id, quantity); err != nil {
				return err
			}
			result.Units += quantity
		}
		return nil
	})
	if err != nil {
		return Result{}, err
	}
	return result, nil
}
