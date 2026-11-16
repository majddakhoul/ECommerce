package domain

import "errors"

const (
	MaxQuantity            int64 = 4294967295
	MaxReservationIDLength       = 64
)

type ReservationStatus string

const (
	StatusReserved ReservationStatus = "reserved"
	StatusReleased ReservationStatus = "released"
)

type ShortageReason int

const (
	ReasonInsufficientStock ShortageReason = iota + 1
	ReasonProductNotFound
)

type StockLevel struct {
	ProductID int64
	Stock     int64
}

type Item struct {
	ProductID int64
	Quantity  int64
}

type ReservationRow struct {
	ProductID int64
	Quantity  int64
	Status    ReservationStatus
}

type Shortage struct {
	ProductID int64
	Requested int64
	Available int64
	Reason    ShortageReason
}

type ReservationResult struct {
	Reserved      bool
	ReservationID string
	Shortages     []Shortage
}

type ReleaseResult struct {
	Released      bool
	ItemsReleased int64
}

var (
	ErrDuplicate       = errors.New("duplicate entry")
	ErrUnavailable     = errors.New("storage unavailable")
	ErrUnauthenticated = errors.New("invalid or missing api key")
	ErrRejected        = errors.New("reservation rejected")
)

type ValidationError struct{ Message string }

func (e *ValidationError) Error() string { return e.Message }

func NewValidationError(message string) error { return &ValidationError{Message: message} }

func IsValidation(err error) bool {
	var target *ValidationError
	return errors.As(err, &target)
}
