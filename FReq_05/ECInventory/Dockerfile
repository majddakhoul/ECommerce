FROM golang:1.24-alpine AS build

RUN apk add --no-cache protobuf protobuf-dev git \
 && go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.36.6 \
 && go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.5.1

WORKDIR /src

COPY proto ./proto
COPY ECInventory ./

RUN mkdir -p gen/inventorypb \
 && protoc \
      --proto_path=proto \
      --go_out=gen/inventorypb --go_opt=paths=source_relative \
      --go-grpc_out=gen/inventorypb --go-grpc_opt=paths=source_relative \
      proto/inventory.proto \
 && go mod tidy \
 && CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/inventory ./cmd/server

FROM alpine:3.20

RUN adduser -D -u 10001 inventory
USER inventory

COPY --from=build /out/inventory /usr/local/bin/inventory

EXPOSE 50051

HEALTHCHECK --interval=5s --timeout=3s --retries=30 CMD ["/usr/local/bin/inventory", "healthcheck"]

ENTRYPOINT ["/usr/local/bin/inventory"]
