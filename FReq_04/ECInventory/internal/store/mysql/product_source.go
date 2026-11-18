package mysql

import (
	"context"
	"database/sql"

	"ecinventory/internal/config"
)

type ProductSource struct {
	db *sql.DB
}

func OpenProductSource(cfg config.Config) (*ProductSource, error) {
	db, err := connect(cfg, cfg.AppDBHost, cfg.AppDBPort, cfg.AppDBUser, cfg.AppDBPassword, cfg.AppDBName)
	if err != nil {
		return nil, err
	}
	return &ProductSource{db: db}, nil
}

func (p *ProductSource) Close() error { return p.db.Close() }

func (p *ProductSource) ProductIDs(ctx context.Context) ([]int64, error) {
	rows, err := p.db.QueryContext(ctx, "SELECT id FROM products ORDER BY id")
	if err != nil {
		return nil, translate(err)
	}
	defer rows.Close()
	ids := make([]int64, 0)
	for rows.Next() {
		var id int64
		if err := rows.Scan(&id); err != nil {
			return nil, translate(err)
		}
		ids = append(ids, id)
	}
	return ids, translate(rows.Err())
}
