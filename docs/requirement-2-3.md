# Requirements 2 and 3: inventory service and performance

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

Both requirements live in [FReq_02_03](../FReq_02_03/README.md). Requirement 2 moves stock out of the Laravel database into an independent Go service reached over gRPC. Requirement 3 makes the read paths fast with caching, ETag, indexes, FULLTEXT search and an optional read replica, and measures each step. They extend [requirement 1](requirement-1.md) without changing the contract of `/api/v1`.

## What it contains

| Part | Folder | Documentation |
| --- | --- | --- |
| Laravel API with `/api/v1`, `/api/v2` (inventory) and `/api/v3` (cache, ETag, FULLTEXT, replica, performance lab) | [ECBackend](../FReq_02_03/ECBackend/README.md) | [Backend README](../FReq_02_03/ECBackend/README.md) |
| Go inventory service: atomic reservation, release, idempotency, seeding | [ECInventory](../FReq_02_03/ECInventory/README.md) | [Inventory README](../FReq_02_03/ECInventory/README.md) |
| gRPC contract | [proto](../FReq_02_03/proto/README.md) | [Contract](../FReq_02_03/proto/README.md) |
| k6 scripts `race.js`, `compare.js`, `consistency.js` | [loadtest](../FReq_02_03/loadtest/README.md) | [Load test guide](../FReq_02_03/loadtest/README.md) |
| Cross-cutting documents | [docs](../FReq_02_03/docs/README.md) | [Architecture](../FReq_02_03/docs/architecture.md), [Docker](../FReq_02_03/docs/docker.md), [Local set-up](../FReq_02_03/docs/local-setup.md), [Testing](../FReq_02_03/docs/testing.md), [Security](../FReq_02_03/docs/security.md), [Troubleshooting](../FReq_02_03/docs/troubleshooting.md), [Verification status](../FReq_02_03/docs/verification-status.md) |
| Measurement guide and recorded numbers | [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md) | [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md) |

## Prerequisites

| Way | Tools |
| --- | --- |
| Docker | Git, Docker Desktop, about 4 GB of free memory, ports 8000, 3307, 3308, 3309, 6379, 8080 and 50051 free |
| Local | Git, PHP 8.2 or newer with `grpc`, Composer, MySQL, Go 1.24, `protoc` and its two Go plugins |
| Load tests | k6, or Docker |

Install them with [environment-setup.md](environment-setup.md).

## Run with Docker

From `FReq_02_03`:

```powershell
Copy-Item .env.example .env
```

```powershell
docker compose up --build
```

The first build compiles the PHP `grpc` extension and takes several minutes. Start-up order is enforced by health checks: the databases and Redis, then the replication job, then the inventory service, then the API, which migrates and seeds.

Open `http://localhost:8000/api/v3/products` after logging in as `admin@example.com` with the password `password`. phpMyAdmin is on `http://localhost:8080`.

```powershell
docker compose down
```

```powershell
docker compose down -v
```

The container table, the variables and the replica set-up are in [docker.md](../FReq_02_03/docs/docker.md).

## Run locally

Create the two databases:

```powershell
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS inventory CHARACTER SET utf8mb4;"
```

### Inventory service

From `FReq_02_03/ECInventory`, generate the gRPC code. If `make` is available:

```powershell
make proto
```

On Windows, where `make` is usually missing, run what the Makefile runs:

```powershell
go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.36.6
go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.5.1
New-Item -ItemType Directory -Force gen/inventorypb
protoc --proto_path=../proto --go_out=gen/inventorypb --go_opt=paths=source_relative --go-grpc_out=gen/inventorypb --go-grpc_opt=paths=source_relative ../proto/inventory.proto
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

The service listens on port 50051 and creates its tables on start. Leave this terminal open. Confirm the port from another terminal:

```powershell
Test-NetConnection 127.0.0.1 -Port 50051
```

`TcpTestSucceeded` must be `True`. If it is `False`, the next step fails with `InventoryUnavailableException`.

### Laravel API

From `FReq_02_03/ECBackend`:

```powershell
composer install
```

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

```powershell
php artisan jwt:secret
```

In `.env` set `DB_DATABASE=auth_system`, `DB_USERNAME=root`, an empty `DB_PASSWORD`, `INVENTORY_API_KEY=change-me`, `CACHE_STORE=file` and `PERFORMANCE_STORE=file`. Then:

```powershell
php artisan migrate --seed
```

```powershell
php artisan storage:link
```

```powershell
php artisan serve --port=8000
```

The product seeder asks the inventory service to store a quantity for every product, so the Go service must be running before `migrate --seed`.

### Reset all data

Product ids change after a fresh migration, so the inventory is seeded last. In `ECBackend`:

```powershell
php artisan migrate:fresh --seed
```

In `ECInventory`:

```powershell
$env:APP_DB_NAME="auth_system"
```

```powershell
go run ./cmd/seed
```

## Differences between local and Docker

| Aspect | Local | Docker |
| --- | --- | --- |
| Cache store | File, slower than Redis | Redis |
| Read replica | Not available, so the replica step is skipped | MySQL 8 GTID replica |
| PHP server | One process on Windows | One process |
| Ports | MySQL on 3306 | 3307, 3308, 3309 |

## Test

From `FReq_02_03/ECBackend`:

```powershell
php artisan test
```

The suite has 148 tests, uses in-memory SQLite and replaces the gRPC gateway with a fake, so it needs neither MySQL nor the Go service.

From `FReq_02_03/ECInventory`:

```powershell
go test ./internal/...
```

The integration tests run against real MySQL and prove the race at the data layer:

```powershell
$env:DB_USER="root"; $env:DB_PASSWORD=""; $env:DB_NAME="inventory"
```

```powershell
go test -tags integration -count=1 -v ./tests/integration/...
```

The end to end race, ten simultaneous orders for a product with stock 1, from `FReq_02_03`:

```powershell
k6 run -e BASE_URL=http://localhost:8000 loadtest/race.js
```

The expected result is `orders_created` 1, `orders_conflict` 9 and `orders_unexpected` 0. With Docker:

```powershell
docker compose --profile tools run --rm k6 run /scripts/race.js
```

## Performance measurement

The ordered procedure is in [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md). It runs steps 0 to 5: baseline, indexes and FULLTEXT, cache and ETag, read replica, N+1, and the immediate-update proof. Prepare the data set first, from `FReq_02_03` with Docker:

```powershell
docker compose exec app php artisan perf:seed --products=50000 --orders=200000
```

Then a step, for example the baseline against the unmodified `v2` routes:

```powershell
docker compose --profile tools run --rm -e VERSION=v2 -e LABEL=step0-v2-baseline -e VUS=20 -e DURATION=60s k6 run /scripts/compare.js
```

Without Docker the same steps work with a separate database, `API_RATE_LIMIT=100000`, `perf:seed --skip-inventory` and `go run ./cmd/seed`, see [local-setup.md](../FReq_02_03/docs/local-setup.md#5-performance-data-set).

## Postman

Import from [`FReq_02_03/ECBackend/postman`](../FReq_02_03/ECBackend/postman): `Requirement-1-v1`, `Requirement-2-v2` (adds the inventory checks) and `Requirement-3-v3` (adds the performance lab and the optimized catalog). Set the base URL to `http://localhost:8000`.

| Flow | Expected |
| --- | --- |
| Product list twice | First `X-Cache: MISS`, then `HIT` |
| Repeat with `If-None-Match` equal to the ETag, including the quotes | `304` with an empty body |
| Update a product, then list | The new value at once |
| Order more than the stock | `409` |
| Stop the inventory service, then order | `503` |

## Results

The measured results of requirement 3, the test counts and the race proof are in [results.md](results.md#requirements-2-and-3). The numbers come from one Windows machine without Redis and without the replica, so the replica step and the Redis figures are not available, see [results.md](results.md#not-demonstrated).

## Continue

[Requirement 4](requirement-4.md) adds queues, real-time updates and the React client.
