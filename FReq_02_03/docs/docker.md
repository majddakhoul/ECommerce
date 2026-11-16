# Docker guide

> [Documentation index](README.md) · [Architecture](architecture.md) · [Local set-up](local-setup.md) · [Troubleshooting](troubleshooting.md)

> Verification status: the Docker configuration was written and reviewed, but it was never built or started by the authors. Every other part of the project was run and tested on a local set-up without Docker, see [verification-status.md](verification-status.md). Expect to fix small problems on the first build, and report them against this file.

## What the compose file builds

The platform is defined in [`docker-compose.yml`](../docker-compose.yml) and uses the container prefix `ec23_`, so it does not collide with other projects.

| Service | Container | Image | Role | Host port |
| --- | --- | --- | --- | --- |
| `app` | `ec23_app` | built from [`ECBackend/Dockerfile`](../ECBackend/Dockerfile) | Laravel API | 8000 |
| `db` | `ec23_db` | `mysql:8.0` | API database, replication source | 3307 |
| `db-replica` | `ec23_db_replica` | `mysql:8.0` | Read replica | 3309 |
| `replica-setup` | `ec23_replica_setup` | `mysql:8.0` | One-shot job that connects the replica to the primary | none |
| `redis` | `ec23_redis` | `redis:7-alpine` | Cache and runtime flags | 6379 |
| `inventory-service` | `ec23_inventory` | built from [`ECInventory/Dockerfile`](../ECInventory/Dockerfile) | Go gRPC service | 50051 |
| `inventory-db` | `ec23_inventory_db` | `mysql:8.0` | Inventory database | 3308 |
| `phpmyadmin` | `ec23_pma` | `phpmyadmin:5` | Database browser for `db` and `inventory-db` | 8080 |
| `inventory-tests` | none | build stage of the inventory image | Go tests (profile `tools`) | none |
| `k6` | none | `grafana/k6:0.54.0` | Load tests (profile `tools`) | none |

Named volumes: `db_data`, `replica_data`, `inventory_db_data`, `product_images`, `app_logs`.

## Prerequisites

- Docker Desktop with Compose v2.
- About 4 GB of free memory for the databases, Redis, and the first build.
- Ports 8000, 3307, 3308, 3309, 6379, 8080 and 50051 free, or changed in `.env`.

## First start

From the `FReq_02_03` folder in PowerShell:

```powershell
Copy-Item .env.example .env
```

```powershell
docker compose up --build
```

On macOS or Linux use `cp .env.example .env` for the first command.

The first build compiles the PHP gRPC extension and takes several minutes. Start-up order is enforced by health checks:

1. `db`, `db-replica`, `inventory-db` and `redis` become healthy.
2. `replica-setup` configures replication and exits.
3. `inventory-service` becomes healthy after it creates its tables.
4. `app` runs its [entrypoint](../ECBackend/docker/entrypoint.sh): clear config, migrate, seed, create the storage link, serve on port 8000.

Open `http://localhost:8000/api/v3/products` to confirm. Demo accounts, password `password`: `admin@example.com` and `user@example.com`.

## Environment file

[`.env.example`](../.env.example) contains development values only. Copy it to `.env`, which is ignored by Git.

| Variable | Default | Meaning |
| --- | --- | --- |
| `APP_KEY` | development value | Laravel encryption key. Generate a new one outside development. |
| `JWT_SECRET` | development value | Signing secret for tokens |
| `INVENTORY_API_KEY` | `change-me` | Shared secret between Laravel and the Go service |
| `APP_PORT` | 8000 | Host port of the API |
| `INVENTORY_PORT` | 50051 | Host port of the gRPC service |
| `ECOMMERCE_DB_PORT` | 3307 | Host port of the API database |
| `INVENTORY_DB_PORT` | 3308 | Host port of the inventory database |
| `REPLICA_DB_PORT` | 3309 | Host port of the replica |
| `REDIS_PORT` | 6379 | Host port of Redis |
| `PMA_PORT` | 8080 | Host port of phpMyAdmin |
| `API_RATE_LIMIT` | 1000000 | Requests per minute per client. High on purpose so load tests are not throttled. Lower it for normal use. |

The database passwords (`secret`, root password `root`) and the replication user (`repl` with `replpass`) are fixed in the compose file and the [MySQL scripts](../docker/mysql). They are development values. Change them before any shared deployment.

## Service details

### app

The image is `php:8.3-cli` with `pdo_mysql`, `zip`, `mbstring`, the `grpc` extension (installed with up to three attempts because the compile is long), and `redis`. Composer installs dependencies without development packages, so the PHPUnit suite is not available inside this image. Run the PHP tests on the host, see [testing.md](testing.md).

The container sets `PERFORMANCE_STORE=redis`, `PERF_REPLICA_ENABLED=false`, `PERF_CACHE_TTL=120` and the inventory connection (`inventory-service:50051`, timeout 2 seconds). It serves with `php artisan serve`, which is a single process, so absolute throughput numbers are lower than with PHP-FPM.

### db and db-replica

MySQL 8 with GTID replication. The primary creates the replication user from [`primary-init.sql`](../docker/mysql/primary-init.sql). `replica-setup` runs [`replica-setup.sh`](../docker/mysql/replica-setup.sh), which waits for both servers, then issues `CHANGE REPLICATION SOURCE TO ... SOURCE_AUTO_POSITION=1` and `START REPLICA`. The replica runs with `--read-only=ON`.

Check replication:

```powershell
docker compose exec db-replica mysql -uroot -proot -e "SHOW REPLICA STATUS\G"
```

Both `Replica_IO_Running` and `Replica_SQL_Running` must be `Yes`. The replica is used by the API only when `PERF_REPLICA_ENABLED` is true, which an administrator switches with the flags endpoint, see [PERFORMANCE.md](../PERFORMANCE.md#step-3---read-replica).

### redis

`redis:7-alpine` with a 256 MB limit, the `allkeys-lru` eviction policy, and persistence disabled. The cache and the runtime flags are rebuilt or reset after a restart.

### inventory-service and inventory-db

The image has two stages. The build stage installs `protoc` and the Go plugins, generates code from [`proto/inventory.proto`](../proto/inventory.proto), runs `go mod tidy`, and compiles a static binary. The runtime stage is `alpine:3.20`, runs as the unprivileged user `inventory`, and has a health check that calls `inventory healthcheck`. The build context is the `FReq_02_03` folder, so the image can see the `proto` folder.

### inventory-tests and k6

Both belong to the profile `tools` and do not start with a plain `docker compose up`. `inventory-tests` uses the build stage, so the Go toolchain is available. `k6` mounts [`loadtest`](../loadtest) read-only as `/scripts` and `loadtest/results` as `/results`, and targets `http://app:8000`.

## Everyday commands

```powershell
docker compose up --build -d
```

```powershell
docker compose ps
```

```powershell
docker compose logs -f app
```

```powershell
docker compose logs -f inventory-service
```

```powershell
docker compose exec app php artisan test
```

```powershell
docker compose down
```

```powershell
docker compose down -v
```

The last command also deletes every volume, which resets all data.

## Measurement inside Docker

```powershell
docker compose exec app php artisan perf:seed --products=50000 --orders=200000
```

```powershell
docker compose --profile tools run --rm k6 run -e VERSION=v2 -e LABEL=step0-baseline /scripts/compare.js
```

The complete ordered procedure, including the replica step that needs this environment, is in [PERFORMANCE.md](../PERFORMANCE.md).

## Failure drills

```powershell
docker compose stop inventory-service
```

Place an order. Laravel must answer `503` and log the failure. Restore with:

```powershell
docker compose start inventory-service
```

## Known gaps

- The stack was never started, so health check timing and the first-build duration are estimates.
- `composer install --no-dev` means `php artisan test` fails inside the `app` container unless the image is changed to install development packages.
- `php artisan serve` is single-process. A production image would use PHP-FPM with Nginx.
- `APP_DEBUG` is `true` and secrets are development values.
