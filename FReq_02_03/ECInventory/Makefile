PROTO_DIR := ../proto
GEN_DIR := gen/inventorypb

.PHONY: proto build test test-race integration vet fmt seed

proto:
	mkdir -p $(GEN_DIR)
	protoc --proto_path=$(PROTO_DIR) \
		--go_out=$(GEN_DIR) --go_opt=paths=source_relative \
		--go-grpc_out=$(GEN_DIR) --go-grpc_opt=paths=source_relative \
		$(PROTO_DIR)/inventory.proto

build: proto
	CGO_ENABLED=0 go build -o bin/inventory ./cmd/server

test:
	go test ./internal/...

test-race:
	go test -race ./internal/...

integration: proto
	go test -tags integration -count=1 ./tests/integration/...

vet:
	go vet ./...

fmt:
	gofmt -l -w .

seed:
	go run ./cmd/seed
