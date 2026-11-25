package service_test

import (
	"testing"

	"ecinventory/internal/domain"
	"ecinventory/internal/validation"
)

func TestMergeItemsSortsAndSums(t *testing.T) {
	merged, err := validation.MergeItems([]domain.Item{{ProductID: 3, Quantity: 1}, {ProductID: 1, Quantity: 2}, {ProductID: 3, Quantity: 4}})
	if err != nil || len(merged) != 2 || merged[0].ProductID != 1 || merged[1].Quantity != 5 {
		t.Fatalf("unexpected %+v %v", merged, err)
	}
}

func TestMergeItemsRejectsOverflow(t *testing.T) {
	_, err := validation.MergeItems([]domain.Item{{ProductID: 1, Quantity: domain.MaxQuantity}, {ProductID: 1, Quantity: 1}})
	if !domain.IsValidation(err) {
		t.Fatalf("expected validation error, got %v", err)
	}
}

func TestReservationIDIsTrimmed(t *testing.T) {
	id, err := validation.ReservationID("  abc ")
	if err != nil || id != "abc" {
		t.Fatalf("unexpected %q %v", id, err)
	}
}
