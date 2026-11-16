# Architecture

> [Documentation index](README.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Security](security.md)

## Overview

The platform has two applications and two data stores of record, plus optional performance infrastructure.

```
Client
  |
  v
Laravel API (ECBackend)  ---- gRPC with x-api-key ---->  Go inventory service (ECInventory)
  |            |                                                   |
  |            +-- Redis (cache and runtime flags)                 |
  v                                                                v
MySQL primary (products, users, orders)                 MySQL inventory (stock, reservations)
  |
  v
MySQL replica (catalog reads)
```

| Part | Technology | Owns |
| --- | --- | --- |
| API | Laravel 12, PHP 8.2 and above | Users, roles, products, carts, orders, authentication |
| Inventory service | Go 1.24, gRPC | Stock levels and reservations |
| API database | MySQL 8 | Everything except stock |
| Inventory database | MySQL 8 | `inventory_items`, `stock_reservations` |
| Cache | Redis 7 | Catalog pages and rows, runtime flags |
| Replica | MySQL 8, GTID | Read-only copy of the API database |

## API versions

The three versions are registered from one route closure in `ECBackend/routes/api.php`, so they share the same controllers where behaviour is the same.

| Version | Requirement | Stock | Read path |
| --- | --- | --- | --- |
| `/api/v1` | 1 | Column inside Laravel | Direct database |
| `/api/v2` | 2 | Go service over gRPC | Direct database plus live stock |
| `/api/v3` | 3 | Go service over gRPC | Cache, ETag, replica, FULLTEXT, plus live stock |

Version 2 and version 3 keep the version 1 response contract, so the Flutter client of [FReq_01](../../README.md) works against all of them.

## Requirement 2 design

### Separation

The two databases are separate schemas. The API database has no stock column. The inventory database has no product table. They are linked only by `product_id`, which Laravel sends in every call. The single exception is the test-data tooling, described under [Seeding](#seeding-across-two-databases).

### Contract

[`proto/inventory.proto`](../proto/inventory.proto) is the only shared artifact. Details are in the [contract guide](../proto/README.md).

### Reservation algorithm

1. Laravel collects every line of the order and sends one `ReserveStock` with a client-generated `reservation_id`.
2. The service opens one transaction. For each item it runs `UPDATE inventory_items SET stock = stock - ? WHERE product_id = ? AND stock >= ?`.
3. If any update affects zero rows, the transaction rolls back, and the answer is `reserved = false` with the list of shortages.
4. Otherwise it stores the reservation lines and commits.
5. Repeating the same `reservation_id` returns the stored result without decrementing again.
6. `ReleaseStock` restores the quantities once and marks the reservation released. A second release is a no-op.
7. A deadlock is retried up to three times.

Because the check and the decrement are one statement, two concurrent orders for the last unit cannot both succeed. This is proved by the tests listed in [testing.md](testing.md#inventory-race-proofs).

### Failure handling

| Situation | Behaviour |
| --- | --- |
| Not enough stock | Laravel answers `409` with the shortages |
| Service down, wrong key, or call longer than 2 seconds | Laravel logs the failure and answers `503` |
| Order cannot be saved after the stock was reserved | Laravel releases the reservation |
| Order cancelled | Laravel releases the reservation once |

## Requirement 3 design

### Request path of `/api/v3/products`

1. `PerformanceHeaders` starts timing and counting queries.
2. `ConditionalGet` compares `If-None-Match` with the ETag of the response and answers `304` when equal.
3. `CatalogService` asks `CatalogCache` for the page. The key contains a global version number, the filters, and the page.
4. On a miss, `CatalogRepository` reads the page from the replica when it is enabled and safe, otherwise from the primary. Search uses FULLTEXT with a LIKE fallback.
5. Stock for the page is always read live with one `GetStocks` call and merged into the cached rows.
6. The response carries `X-Cache`, `X-DB-Queries`, `X-Response-Time-Ms` and `X-Data-Source`.

### Why stock is never cached

Stock changes on every order. Caching it would show wrong quantities and would hide the race protection. The cache stores only catalog data, so an order never invalidates it.

### Invalidation

`ProductObserver` bumps the global cache version when a product is saved or deleted. All older keys become unreachable at once and expire by TTL or by LRU. The `invalidation` flag can be switched off at runtime to demonstrate the stale reads that invalidation prevents.

### Replica guard

`ReplicaGuard` sends reads to the replica only when the flag is on and the caller has not written within `PERF_REPLICA_STICKY_SECONDS`. This gives read-your-writes for the caller who just changed data.

### Runtime flags

`Flags` stores `cache`, `etag`, `replica`, `fulltext` and `invalidation` in the store named by `PERFORMANCE_STORE`, so an administrator can switch each mechanism on or off between measurements without a restart.

### Lab API

Administrator-only endpoints under `/api/v3/performance`: `status`, `flags`, `cache/flush`, `explain`, `compare`. Artisan commands: `perf:seed` and `perf:indexes add|drop|status`.

## Seeding across two databases

Stock lives in the inventory database, so seeding has two parts.

| Step | Command | Effect |
| --- | --- | --- |
| API data | `php artisan migrate:fresh --seed` | Users, roles, products. The product seeder asks the inventory service to set the stock of each product. |
| Inventory data | `go run ./cmd/seed` in `ECInventory` | Empties the inventory tables, reads every product id from the API database, stores one random quantity per product |

`perf:seed --skip-inventory` creates large API data without touching inventory, and the Go seeder then fills the inventory. Without that flag, `perf:seed` writes the stock rows directly through the `inventory_seed` connection, which is a test-data shortcut only.

## Code layout principles

- Laravel: controllers stay thin, services hold business rules, repositories hold queries, resources shape responses, middleware holds cross-cutting concerns.
- Go: `domain` has no dependencies, `service` depends on interfaces, `store/mysql` and `transport/grpcapi` implement them, `cmd` wires everything.
- Both sides depend on abstractions of each other: Laravel on `InventoryGatewayInterface`, Go on `service.Store`. Tests replace both with fakes.
