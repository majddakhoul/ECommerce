package mysql

import (
	"context"

	"ecinventory/internal/domain"
)

type reservationRepository struct {
	q queryer
}

func (r *reservationRepository) FindByReservationID(ctx context.Context, reservationID string) ([]domain.ReservationRow, error) {
	rows, err := r.q.QueryContext(ctx,
		"SELECT product_id, quantity, status FROM stock_reservations WHERE reservation_id = ? ORDER BY product_id",
		reservationID)
	if err != nil {
		return nil, translate(err)
	}
	defer rows.Close()
	result := make([]domain.ReservationRow, 0)
	for rows.Next() {
		var row domain.ReservationRow
		var status string
		if err := rows.Scan(&row.ProductID, &row.Quantity, &status); err != nil {
			return nil, translate(err)
		}
		row.Status = domain.ReservationStatus(status)
		result = append(result, row)
	}
	return result, translate(rows.Err())
}

func (r *reservationRepository) InsertMany(ctx context.Context, reservationID string, items []domain.Item) error {
	for _, item := range items {
		_, err := r.q.ExecContext(ctx,
			"INSERT INTO stock_reservations (reservation_id, product_id, quantity, status) VALUES (?, ?, ?, ?)",
			reservationID, item.ProductID, item.Quantity, string(domain.StatusReserved))
		if err != nil {
			return translate(err)
		}
	}
	return nil
}

func (r *reservationRepository) MarkReleased(ctx context.Context, reservationID string, productID int64) (bool, error) {
	result, err := r.q.ExecContext(ctx,
		"UPDATE stock_reservations SET status = ? WHERE reservation_id = ? AND product_id = ? AND status = ?",
		string(domain.StatusReleased), reservationID, productID, string(domain.StatusReserved))
	if err != nil {
		return false, translate(err)
	}
	affected, err := result.RowsAffected()
	return affected > 0, translate(err)
}
