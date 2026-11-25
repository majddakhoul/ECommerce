# FReq_02_03 - Requirements 2 and 3

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter&logoColor=white" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis 7">
<img src="https://img.shields.io/badge/k6-load%20tests-7D64FF?logo=k6&logoColor=white" alt="k6">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../README.md) · [Requirement 1](../FReq_01/README.md) · [Requirements 2 and 3](README.md) · [Requirement 4](../FReq_04/README.md) · [Requirement 5](../FReq_05/README.md) · [Documentation hub](../docs/README.md)

Part of the [ECommerce](../README.md) repository. Requirement 1 is [FReq_01](../FReq_01/README.md). This folder holds requirements 2 and 3 together, because requirement 3 builds directly on the service split of requirement 2: one platform, one Laravel API with the routes `/api/v1`, `/api/v2` and `/api/v3`, and one Go inventory service.

[Documentation index](docs/README.md) · [Architecture](docs/architecture.md) · [Docker](docs/docker.md) · [Local set-up](docs/local-setup.md) · [Testing](docs/testing.md) · [Security](docs/security.md) · [Troubleshooting](docs/troubleshooting.md) · [Verification status](docs/verification-status.md)

---

## Table of Contents

- [Repository Layout](#repository-layout)
- [Components](#components)
- [Choose How to Run](#choose-how-to-run)
- [Demo Accounts](#demo-accounts)
- [Requirement 2 - Communication between services](#requirement-2---communication-between-services)
- [Requirement 3 - Performance and caching](#requirement-3---performance-and-caching)
- [How the Two Requirements Connect](#how-the-two-requirements-connect)
- [Requirement 4](#requirement-4)
- [Requirement 5](#requirement-5)
- [Verification Status](#verification-status)
- [Tech Stack](#tech-stack)
- [License](#license)

---

## Repository Layout

```text
FReq_02_03/
├── ECBackend/            Laravel API with v1, v2, v3, lab API, Postman collections
├── ECInventory/          Go gRPC inventory service and seeder
├── proto/                The gRPC contract
├── loadtest/             k6 scripts
├── docker/               MySQL replication scripts
├── docs/                 Cross-cutting documentation
├── docker-compose.yml    The whole platform
├── .env.example          Development values
└── PERFORMANCE.md        Measurement guide and results
```

---

## Components

| Component | Purpose | Guide |
|---|---|---|
| [`ECBackend`](ECBackend) | Laravel API with v1, v2 and v3, lab API, Postman collections. Requirement 1 version: [FReq_01/ECBackend](../FReq_01/ECBackend/README.md) | [README](ECBackend/README.md) |
| [`ECInventory`](ECInventory) | Go gRPC inventory service and seeder | [README](ECInventory/README.md) |
| [`proto`](proto) | The gRPC contract shared by both | [README](proto/README.md) |
| [`loadtest`](loadtest) | k6 scripts | [README](loadtest/README.md) |
| [`docker`](docker) | MySQL replication scripts | [docker.md](docs/docker.md#db-and-db-replica) |
| [`docker-compose.yml`](docker-compose.yml) | The whole platform | [docker.md](docs/docker.md) |
| [`PERFORMANCE.md`](PERFORMANCE.md) | Measurement procedure and results | [PERFORMANCE.md](PERFORMANCE.md) |

| Requirement | Routes | Postman collection |
|---|---|---|
| 1. API and security | `/api/v1` | [`Requirement-1-v1`](ECBackend/postman/Requirement-1-v1.postman_collection.json) |
| 2. Communication between services | `/api/v2` | [`Requirement-2-v2`](ECBackend/postman/Requirement-2-v2.postman_collection.json) |
| 3. Performance and caching | `/api/v3` | [`Requirement-3-v3`](ECBackend/postman/Requirement-3-v3.postman_collection.json) |

---

## Choose How to Run

| Option | Use it when | Status | Guide |
|---|---|---|---|
| Local without Docker | You have PHP, Go and MySQL, for example XAMPP | Run and verified | [local-setup.md](docs/local-setup.md) |
| Docker Compose | You want Redis, the replica and a reproducible set-up | Written, never executed | [docker.md](docs/docker.md) |

Docker quick start, in PowerShell from this folder:

```powershell
Copy-Item .env.example .env
```

```powershell
docker compose up --build
```

| Container | Role | Port |
|---|---|---|
| `ec23_app` | Laravel API | 8000 |
| `ec23_db` | API database, replication source | 3307 |
| `ec23_db_replica` | Read replica | 3309 |
| `ec23_redis` | Cache and runtime flags | 6379 |
| `ec23_inventory` | Go gRPC inventory service | 50051 |
| `ec23_inventory_db` | Inventory database | 3308 |
| `ec23_pma` | phpMyAdmin | 8080 |

Stop with `docker compose down`, reset all data with `docker compose down -v`.

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

---

## Requirement 2 - Communication between services

Stock management is split out of Laravel into an independent Go service with its own MySQL database. The two services share one gRPC contract and nothing else. The design is in [architecture.md](docs/architecture.md#requirement-2-design).

```text
Client -> Laravel API --gRPC + x-api-key--> Go inventory service
             |                                   |
       MySQL (products, orders)           MySQL (stock, reservations)
```

| Requirement | Answer |
|---|---|
| Shared contract | [`proto/inventory.proto`](proto/inventory.proto), generated into Go and PHP |
| Another language, own database | [`ECInventory`](ECInventory/README.md), Go and MySQL 8 |
| Get, reserve, release | `GetStock`, `GetStocks`, `ListAvailableProductIds`, `SetStock`, `DeleteStock`, `ReserveStock`, `ReleaseStock` |
| Insufficient stock | `reserved = false` with shortages, Laravel answers `409` |
| Service down or slow | 2 second deadline, Laravel answers `503` and logs the failure |
| Save fails after reserving | The reservation is released |
| Race condition | One atomic `UPDATE ... WHERE stock >= ?` per item inside a transaction |
| Protected service | `x-api-key` metadata, checked by a Go interceptor |
| Separate containers | API, API database, inventory service, inventory database |

Placing an order: Laravel validates, makes one `ReserveStock` call for all lines, answers `409` if anything is short, otherwise saves the order with the reservation id and releases the reservation if saving fails. Cancelling releases it once.

Prove it, details in [testing.md](docs/testing.md):

```powershell
php artisan test
```

```powershell
go test ./internal/...
```

```powershell
go test -tags integration -count=1 -v ./tests/integration/...
```

```powershell
k6 run -e BASE_URL=http://localhost:8000 loadtest/race.js
```

The first runs in `ECBackend`, the second and third in `ECInventory`, the fourth here. The race script sends ten simultaneous HTTP orders for a product with stock 1: `orders_created` must be 1, `orders_conflict` 9, `orders_unexpected` 0. With Docker, the same checks run as `docker compose run --rm inventory-tests` and `docker compose --profile tools run --rm k6 run /scripts/race.js`.

---

## Requirement 3 - Performance and caching

The read path of the catalog is faster and stays correct. The design is in [architecture.md](docs/architecture.md#requirement-3-design).

```text
Client -> Laravel API (/api/v3)
           |-- Redis: catalog pages and rows (versioned keys, TTL, LRU)
           |-- MySQL primary: writes, and reads right after a write
           |-- MySQL replica: catalog reads (GTID replication)
           '-- gRPC -> Go inventory service: live stock, never cached
```

| Requirement | Answer |
|---|---|
| Large seeded data | `php artisan perf:seed` inserts 50,000 products and 200,000 orders with lines in batches |
| Baseline | The `compare` lab endpoint and k6 against `/api/v2` before any change |
| EXPLAIN, indexes, N+1 | `GET /api/v3/performance/explain`, `php artisan perf:indexes add|drop|status`, the `orders` comparison |
| Cache with TTL and invalidation | Redis, versioned keys, TTL with jitter, invalidation by a product observer |
| Conditional requests | `ETag`, `If-None-Match`, `304` |
| Read replica | MySQL 8 replica with GTID auto-position and a read-your-writes guard |
| Load test before and after | k6 `compare.js` with p50, p95, p99 and requests per second |
| Immediate update proof | k6 `consistency.js`, zero stale reads |
| Numbers | [`PERFORMANCE.md`](PERFORMANCE.md#results) |

Measured on the local set-up (file cache, no replica): search 43.9 times faster in process, the N+1 query count from 31 to 3, zero stale reads with invalidation on and 49 of 50 with it off. The load-test tail latency improved only with the indexes, not with the cache, and the reasons are in the [findings](PERFORMANCE.md#findings). The replica and Redis were not measured.

---

## How the Two Requirements Connect

- The stock is never cached, so requirement 3 reuses the gRPC calls of requirement 2 (`GetStocks` for every list page) and stays correct.
- An order changes stock only, so it does not invalidate the product cache.
- `/api/v2` is the baseline that `/api/v3` is measured against, on the same data.

---

## Requirement 4

[FReq_04](../FReq_04/README.md) builds on this platform. It keeps `/api/v1` to `/api/v3`, the Go inventory service and its contract, and adds two paths over the same controllers: `/api/v4/sync` as the baseline and `/api/v4/async` with RabbitMQ jobs and Reverb events.

- The inventory service gains one optional behaviour: it publishes `stock.changed` to RabbitMQ when a call carries the metadata `x-publish-events: 1`. Calls without it behave exactly as described here. See [FReq_04/ECInventory/README.md](../FReq_04/ECInventory/README.md#stock-events).
- The v4 catalog does not use the cache of requirement 3, because live quantities must never be served stale.
- Both stacks use the same host ports (8000, 3307, 3308, 6379, 8080, 50051), so run only one compose project at a time.

---

## Requirement 5

[FReq_05](../FReq_05/README.md) scales this platform out. The Go inventory service and its contract are shared by three Laravel instances, the Redis cache of requirement 3 becomes the shared state of the cart and the locks, and nginx distributes the requests. The word replica in this document means a database read replica. In requirement 5 the copies of the Laravel application are called instances. See [scaling.md](../FReq_05/docs/scaling.md).

---

## Verification Status

| Area | Status |
|---|---|
| Laravel tests | 148 passing |
| Go unit and integration tests | Passing, integration on real MySQL |
| Race condition proof | Passing, one order created and nine conflicts out of ten |
| k6 steps 0 to 2, consistency, explain | Measured, see [PERFORMANCE.md](PERFORMANCE.md#results) |
| Redis, read replica, Docker stack | Not run, see [verification-status.md](docs/verification-status.md) |

---

## Tech Stack

| Layer | Technology |
|---|---|
| API | Laravel 12, PHP 8.2+, `tymon/jwt-auth`, `spatie/laravel-permission`, `grpc/grpc` |
| Inventory service | Go 1.24, gRPC, protobuf, MySQL |
| Data | MySQL 8 with GTID replication, Redis 7 |
| Tooling | Docker Compose, PHPUnit, Go test, k6, Postman |

---

## License

This project is released under the [MIT License](../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
