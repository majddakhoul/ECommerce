# Delivery Report — Task 2 and Task 3

## Delivered

- Independent Go inventory service with generated Go/PHP gRPC stubs.
- Separate inventory MySQL database and Docker Compose service.
- Atomic multi-item reservation with serializable transaction, row locks, deterministic lock order, idempotency key, and idempotent release.
- Laravel gRPC client with timeout/error mapping, product synchronization, order reservation ID, compensation release, and local Null client for isolated tests.
- Redis product-list/category cache with version invalidation.
- ETag/conditional GET middleware returning `304 Not Modified`.
- Configurable `mysql_read` connection for a read replica; primary remains authoritative for stock/order reads and writes.
- Composite indexes, EXPLAIN documentation, and k6 load-test scenario for p50/p95/p99.
- Docker files, environment variables, migration, command `inventory:sync`, implementation README, and focused tests.

## Verification

- PHP lint: all PHP files pass.
- SQLite migration: fresh migration and seed pass, including reservation column and performance indexes.
- Focused tests: `InventoryClientTest` and `PerformanceCachingTest` pass; PHPUnit reports the feature assertions as successful (the two ETag tests are displayed as warnings because the project’s test helper reads a missing optional file).
- Existing Order tests covering checkout, duplicate-line merge, insufficient stock rollback, and cancellation stock restoration pass.
- Go formatting completed. Full `go test` could not download modules in this sandbox because external Go archive downloads ended with `unexpected EOF`; dependencies and pinned versions are declared in `inventory-service/go.mod` for Docker/CI.

## Run

```bash
cd ECBackend
cp .env.example .env
docker compose up --build
```

For local tests without Docker, use `INVENTORY_ENABLED=false` and `PRODUCT_CACHE_ENABLED=false`. For performance comparison, follow `performance/README.md` and run `performance/k6/products.js` once with cache disabled and once enabled.
