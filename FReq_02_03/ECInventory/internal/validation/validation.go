package validation

import (
	"fmt"
	"sort"
	"strings"

	"ecinventory/internal/domain"
)

func PositiveID(value int64, field string) (int64, error) {
	if value <= 0 {
		return 0, domain.NewValidationError(fmt.Sprintf("%s must be a positive integer.", field))
	}
	return value, nil
}

func Quantity(value int64, field string, allowZero bool) (int64, error) {
	minimum := int64(1)
	if allowZero {
		minimum = 0
	}
	if value < minimum || value > domain.MaxQuantity {
		return 0, domain.NewValidationError(fmt.Sprintf("%s must be an integer between %d and %d.", field, minimum, domain.MaxQuantity))
	}
	return value, nil
}

func ReservationID(value string) (string, error) {
	trimmed := strings.TrimSpace(value)
	if trimmed == "" || len(trimmed) > domain.MaxReservationIDLength {
		return "", domain.NewValidationError(fmt.Sprintf("reservation_id must be 1 to %d characters.", domain.MaxReservationIDLength))
	}
	return trimmed, nil
}

func UniqueIDs(values []int64) ([]int64, error) {
	seen := make(map[int64]struct{}, len(values))
	ids := make([]int64, 0, len(values))
	for _, value := range values {
		id, err := PositiveID(value, "product_id")
		if err != nil {
			return nil, err
		}
		if _, exists := seen[id]; exists {
			continue
		}
		seen[id] = struct{}{}
		ids = append(ids, id)
	}
	return ids, nil
}

func MergeItems(items []domain.Item) ([]domain.Item, error) {
	if len(items) == 0 {
		return nil, domain.NewValidationError("items must contain at least one entry.")
	}
	totals := make(map[int64]int64, len(items))
	for _, item := range items {
		id, err := PositiveID(item.ProductID, "product_id")
		if err != nil {
			return nil, err
		}
		quantity, err := Quantity(item.Quantity, "quantity", false)
		if err != nil {
			return nil, err
		}
		totals[id] += quantity
		if totals[id] > domain.MaxQuantity {
			return nil, domain.NewValidationError("quantity is too large.")
		}
	}
	merged := make([]domain.Item, 0, len(totals))
	for id, quantity := range totals {
		merged = append(merged, domain.Item{ProductID: id, Quantity: quantity})
	}
	sort.Slice(merged, func(i, j int) bool { return merged[i].ProductID < merged[j].ProductID })
	return merged, nil
}
