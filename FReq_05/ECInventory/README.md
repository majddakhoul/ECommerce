# ECInventory

> Part of the [ECommerce](../../README.md) repository, requirement 5 ([FReq_05](../README.md)). This service owns the stock of every product and, since requirement 4, publishes a `stock.changed` event to RabbitMQ when a quantity changes. The Laravel API in [`ECBackend`](../ECBackend/README.md) calls it over gRPC. The contract is [`proto/inventory.proto`](../proto/README.md). See also: [documentation index](../docs/README.md), [architecture](../docs/architecture.md), [reliability](../docs/reliability.md), [Docker guide](../docs/docker.md), [local set-up](../docs/local-setup.md), [testing](../docs/testing.md).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../../FReq_04/README.md) · [Requirement 5](../README.md)

Requirements 2 and 3 version of this service: [FReq_02_03/ECInventory](../../FReq_02_03/ECInventory/README.md).

A Go gRPC service with its own MySQL database. It is the only place where quantities are stored, changed and reserved.

---

## Table of Contents

- [Why a separate service](#why-a-separate-service)
- [Architecture](#architecture)
- [Calls](#calls)
- [Reservations](#reservations)
- [Errors](#errors)
- [Stock events](#stock-events)
- [Configuration](#configuration)
- [Running](#running)
- [Seeding](#seeding)
- [Tests](#tests)
- [Generated code](#generated-code)

---

## Why a separate service

Stock is the data that several users change at the same moment. Keeping it in one small service with one database gives a single source of truth, an atomic place to decide whether an order can be served, and a boundary that can be scaled and deployed on its own. Laravel keeps only the product description and price.

---

## Architecture

The code follows the dependency rule: outer layers depend on inner layers, never the other way round.

```
cmd/server              composition root: config, store, service, gRPC server, graceful shutdown
internal/domain         types and errors, no dependencies
internal/validation     input rules (ids, quantities, reservation ids, merging of duplicate items)
internal/service        business rules, depends on the Store port only
internal/store/mysql    MySQL adapter for the Store port (repositories, transactions, retries)
internal/transport/grpcapi   gRPC handlers, interceptors (recovery, logging, API key), error mapping
internal/config         environment configuration
gen/inventorypb         code generated from proto/inventory.proto at build time
tests/integration       tests against a real MySQL (build tag integration)
```

`internal/service` declares the interfaces it needs in `ports.go` (`InventoryRepository`, `ReservationRepository`, `Store`). The MySQL package implements them, and the unit tests implement them in memory. That is why the business rules are tested without a database.

---

## Calls

| Call | Purpose |
|---|---|
| `Ping` | Health check, open without an API key |
| `GetStock` | Quantity of one product |
| `GetStocks` | Quantities of many products in one call (the list page) |
| `ListAvailableProductIds` | Ids with stock above zero (the `in_stock` filter) |
| `SetStock` | Create or replace a quantity |
| `DeleteStock` | Remove the record of a deleted product |
| `ReserveStock` | Take stock for an order, all items or none |
| `ReleaseStock` | Give a reservation back, at most once |

---

## Reservations

`ReserveStock` runs in one transaction. For each item, sorted by product id so that two orders never lock rows in opposite order, it executes:

```sql
UPDATE inventory_items SET stock = stock - ? WHERE product_id = ? AND stock >= ?
```

The row lock taken by this statement makes the check and the decrement a single atomic step. If one item cannot be taken the transaction rolls back and nothing changes. Ten concurrent orders for a product with stock 1 therefore produce exactly one reservation.

- Duplicate items in one request are merged before they are applied.
- The same `reservation_id` sent twice decrements once. If two identical requests race, the second insert hits the unique key, the transaction rolls back and is replayed, and the replay reads the first one.
- `ReleaseStock` marks each reservation row as released before it adds the quantity back, so a second release does nothing.
- Deadlocks and lock timeouts are retried up to three times.

---

## Errors

| Situation | gRPC status |
|---|---|
| Invalid id, quantity or reservation id | `INVALID_ARGUMENT` |
| Missing or wrong `x-api-key` | `UNAUTHENTICATED` |
| Database unreachable | `UNAVAILABLE` |
| Deadline reached | `DEADLINE_EXCEEDED` |
| Anything else | `INTERNAL` |

Not enough stock is not an error: `ReserveStock` answers `reserved = false` with the list of shortages, and Laravel turns it into HTTP 409.

---

## Stock events

When a quantity changes, the service publishes one message to the topic exchange `ec.events` with the routing key `stock.changed`.

| Trigger | `reason` |
|---|---|
| `SetStock` | `set` |
| `DeleteStock` | `deleted` |
| `ReserveStock` (one event per product) | `reserved` |
| `ReleaseStock` (one event per product) | `released` |

```json
{
  "event_id": "b2d6f0c4-6a6e-4e0e-9b52-2a6f4d7f1c11",
  "type": "stock.changed",
  "product_id": 12,
  "stock": 7,
  "deleted": false,
  "reason": "reserved",
  "version": 1788044301123456,
  "occurred_at": "2026-10-27T10:15:01.123456Z"
}
```

Rules:

- Publishing is opt-in per call. Laravel adds the gRPC metadata `x-publish-events: 1` only on the async path. An interceptor turns it into a context flag. Without it nothing is published, so the sync path and the seeder stay silent.
- The event is handed to the publisher after the database transaction commits, so a rolled-back change is never announced.
- The publisher is a buffered goroutine (`EVENT_BUFFER`, default 1024) with publisher confirms. It reconnects with exponential back-off. When the buffer is full the event is dropped and logged. This is best effort, not a transactional outbox, see [architecture](../docs/architecture.md#known-limits).
- `version` is the Unix time in microseconds, and consumers ignore an event older than the last one applied.
- With `RABBITMQ_HOST` empty no publisher is created and the service behaves exactly as in requirement 2.
- The service declares the exchange, the queue `ec.stock.changed` and its dead letter objects with the same arguments as `php artisan messaging:topology`.

Code: `internal/events` (model and context flag), `internal/events/rabbitmq` (publisher), `internal/events/rabbitmq/broker` (AMQP adapter), `internal/service/inventory.go` (publishing points), `internal/transport/grpcapi/interceptors.go` (metadata).

---

## Configuration

| Variable | Default | Meaning |
|---|---|---|
| `GRPC_HOST` / `GRPC_PORT` | `0.0.0.0` / `50051` | Listen address |
| `INVENTORY_API_KEY` | empty | Key expected in the `x-api-key` metadata. Empty disables the check. |
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` | local MySQL | Inventory database |
| `DB_MAX_OPEN_CONNS`, `DB_MAX_IDLE_CONNS`, `DB_CONN_MAX_LIFETIME_SECONDS` | `25`, `10`, `300` | Connection pool |
| `DB_MIGRATE_ATTEMPTS`, `DB_MIGRATE_DELAY_SECONDS` | `30`, `2` | Wait for the database at start-up |
| `SHUTDOWN_TIMEOUT_SECONDS` | `10` | Time allowed for in-flight calls on SIGTERM |
| `RABBITMQ_HOST` | empty | Broker host. Empty disables event publishing |
| `RABBITMQ_PORT`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_VHOST` | `5672`, `guest`, `guest`, `/` | Broker connection |
| `RABBITMQ_EVENTS_EXCHANGE`, `RABBITMQ_STOCK_ROUTING_KEY`, `RABBITMQ_STOCK_QUEUE` | `ec.events`, `stock.changed`, `ec.stock.changed` | Event routing |
| `RABBITMQ_STOCK_DLX`, `RABBITMQ_STOCK_DEAD_KEY`, `RABBITMQ_STOCK_DLQ` | `ec.events.dlx`, `stock.changed.dead`, `ec.stock.changed.dlq` | Dead letter routing |
| `EVENT_BUFFER` | `1024` | Events held in memory while the broker is slow or down |
| `APP_DB_HOST`, `APP_DB_PORT`, `APP_DB_USER`, `APP_DB_PASSWORD`, `APP_DB_NAME` | `127.0.0.1`, `3306`, `root`, empty, `auth_system` | Application database, read only by the seeder to list product ids |

The tables `inventory_items` and `stock_reservations` are created at start-up.

---

## Running

### With Docker

The service is built and started by the compose file one level up:

```bash
docker compose up --build inventory-service
```

The image build installs `protoc`, generates the Go code from `proto/inventory.proto` and compiles a static binary into a small runtime image. The container healthcheck runs `inventory healthcheck`, which pings the server. The Docker configuration has not been executed yet, see the [Docker guide](../docs/docker.md).

### Without Docker

Requirements: Go 1.24, `protoc`, `protoc-gen-go`, `protoc-gen-go-grpc`, and a MySQL database named `inventory`. In PowerShell:

```powershell
make proto
```

```powershell
go mod tidy
```

```powershell
$env:DB_USER="root"; $env:DB_PASSWORD=""; $env:DB_NAME="inventory"; $env:INVENTORY_API_KEY="change-me"
```

```powershell
go run ./cmd/server
```

The tables are created on start. The complete set-up of both services is in [local-setup.md](../docs/local-setup.md).

---

## Seeding

`cmd/seed` empties `inventory_items` and `stock_reservations`, reads every product id from the application database and stores one quantity per product.

```bash
go run ./cmd/seed
go run ./cmd/seed -min 20 -max 300 -seed 5
```

The same `-seed` value always gives the same quantities. The command stops without touching the inventory when the application database has no products.

To reset both databases, run `php artisan migrate:fresh --seed` in `ECBackend` first, then `go run ./cmd/seed`. Product ids change after a fresh migration, so the seeder must run last.

---

## Tests

Unit tests need only the Go standard library:

```bash
go test -race ./internal/...
```

Inside the Docker build image the race detector is not available (it needs a C compiler), so use `docker compose run --rm inventory-tests go test ./internal/...` there and `go test -race ./internal/...` on a machine with Go and gcc.

The event tests cover the event model, the publisher (buffering, confirms, reconnect, full buffer, draining on close, with a fake broker) and the service publishing points (once after commit, nothing without the context flag). The AMQP adapter itself is only compiled in the Docker build and has no unit test.

They cover validation, merging, all-or-nothing reservation, shortage reasons, idempotent replay, release once, the retry on a duplicate insert, and 50 goroutines competing for one unit of stock.

Integration tests run against the real MySQL of the compose file:

```bash
docker compose --profile tools run --rm inventory-tests
```

They prove the same guarantees on InnoDB: ten concurrent reservations for a stock of one give one success, a release restores stock exactly once, and eight identical concurrent reservations decrement once.

---

## Generated code

`gen/inventorypb` is generated and not committed. To work on the service outside Docker, install `protoc`, `protoc-gen-go` and `protoc-gen-go-grpc`, then run `make proto`. `go.sum` is committed. After changing dependencies run `go mod tidy` and commit both `go.mod` and `go.sum`.

---

## License

This project is released under the [MIT License](../../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
