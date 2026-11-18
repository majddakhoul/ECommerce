package mysql

import "context"

func (s *Store) Reset(ctx context.Context) error {
	for _, statement := range []string{
		"TRUNCATE TABLE stock_reservations",
		"TRUNCATE TABLE inventory_items",
	} {
		if _, err := s.db.ExecContext(ctx, statement); err != nil {
			return translate(err)
		}
	}
	return nil
}
