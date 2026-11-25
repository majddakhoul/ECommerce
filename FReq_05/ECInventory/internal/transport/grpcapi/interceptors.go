package grpcapi

import (
	"context"
	"crypto/subtle"
	"log/slog"
	"strings"
	"time"

	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"

	"ecinventory/internal/domain"
	"ecinventory/internal/events"
)

const (
	apiKeyHeader        = "x-api-key"
	publishEventsHeader = "x-publish-events"
	pingMethod          = "/inventory.v1.InventoryService/Ping"
)

func Authentication(apiKey string) grpc.UnaryServerInterceptor {
	return func(ctx context.Context, req any, info *grpc.UnaryServerInfo, next grpc.UnaryHandler) (any, error) {
		if apiKey == "" || info.FullMethod == pingMethod {
			return next(ctx, req)
		}
		md, _ := metadata.FromIncomingContext(ctx)
		values := md.Get(apiKeyHeader)
		if len(values) == 0 || subtle.ConstantTimeCompare([]byte(values[0]), []byte(apiKey)) != 1 {
			return nil, toStatus(domain.ErrUnauthenticated)
		}
		return next(ctx, req)
	}
}

func PublishEvents() grpc.UnaryServerInterceptor {
	return func(ctx context.Context, req any, info *grpc.UnaryServerInfo, next grpc.UnaryHandler) (any, error) {
		if md, ok := metadata.FromIncomingContext(ctx); ok {
			if values := md.Get(publishEventsHeader); len(values) > 0 && values[0] == "1" {
				ctx = events.WithPublishing(ctx)
			}
		}
		return next(ctx, req)
	}
}

func Logging() grpc.UnaryServerInterceptor {
	return func(ctx context.Context, req any, info *grpc.UnaryServerInfo, next grpc.UnaryHandler) (any, error) {
		startedAt := time.Now()
		resp, err := next(ctx, req)
		rpc := info.FullMethod[strings.LastIndex(info.FullMethod, "/")+1:]
		elapsed := float64(time.Since(startedAt).Microseconds()) / 1000
		code := status.Code(err)
		switch {
		case err == nil:
			slog.Info("rpc", "rpc", rpc, "ms", elapsed, "outcome", "ok")
		case code == codes.Internal || code == codes.Unavailable:
			slog.Error("rpc failed", "rpc", rpc, "ms", elapsed, "code", code.String())
		default:
			slog.Warn("rpc rejected", "rpc", rpc, "ms", elapsed, "code", code.String())
		}
		return resp, err
	}
}

func Recovery() grpc.UnaryServerInterceptor {
	return func(ctx context.Context, req any, info *grpc.UnaryServerInfo, next grpc.UnaryHandler) (resp any, err error) {
		defer func() {
			if recovered := recover(); recovered != nil {
				slog.Error("rpc panic", "rpc", info.FullMethod, "panic", recovered)
				err = status.Error(codes.Internal, "Internal inventory error.")
			}
		}()
		return next(ctx, req)
	}
}
