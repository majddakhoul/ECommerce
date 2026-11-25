package grpcapi

import (
	"context"
	"errors"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"

	"ecinventory/internal/domain"
)

func toStatus(err error) error {
	switch {
	case err == nil:
		return nil
	case errors.Is(err, domain.ErrUnauthenticated):
		return status.Error(codes.Unauthenticated, "Invalid or missing API key.")
	case domain.IsValidation(err):
		return status.Error(codes.InvalidArgument, err.Error())
	case errors.Is(err, domain.ErrUnavailable):
		return status.Error(codes.Unavailable, "The inventory database is unavailable.")
	case errors.Is(err, context.DeadlineExceeded):
		return status.Error(codes.DeadlineExceeded, "The request deadline was exceeded.")
	case errors.Is(err, context.Canceled):
		return status.Error(codes.Canceled, "The request was canceled.")
	default:
		return status.Error(codes.Internal, "Internal inventory error.")
	}
}
