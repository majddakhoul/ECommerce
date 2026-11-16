# Local set-up without Docker

> [Documentation index](README.md) · [Docker guide](docker.md) · [Testing](testing.md) · [Troubleshooting](troubleshooting.md)

This is the set-up on which the project was developed and verified: XAMPP MySQL on Windows, PHP, Go, and Windows PowerShell 5.1. Redis and the replica are not part of it, see [Differences from Docker](#differences-from-docker).

## Prerequisites

| Tool | Version | Used for |
| --- | --- | --- |
| PHP | 8.2 or newer, with `pdo_mysql`, `mbstring`, `zip`, and the `grpc` extension | Laravel API |
| Composer | 2 | PHP dependencies |
| MySQL or MariaDB | 8 recommended | Both databases |
| Go | 1.24 | Inventory service |
| `protoc`, `protoc-gen-go`, `protoc-gen-go-grpc` | see the [Dockerfile](../ECInventory/Dockerfile) for the versions | Generating Go code from the contract |
| k6 | recent | Load tests, optional |

## 1. Databases

Create two empty databases. With XAMPP MySQL on port 3306, user `root`, and no password:

```powershell
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS inventory CHARACTER SET utf8mb4;"
```

## 2. Inventory service

From `FReq_02_03/ECInventory`:

```powershell
make proto
```

If `make` is not available, run the `protoc` command from the [Makefile](../ECInventory/Makefile) by hand. Then:

```powershell
go mod tidy
```

Set the configuration for the current terminal:

```powershell
$env:DB_USER="root"; $env:DB_PASSWORD=""; $env:DB_NAME="inventory"; $env:INVENTORY_API_KEY="change-me"
```

```powershell
go run ./cmd/server
```

The service listens on port 50051 and creates its tables on start. Leave this terminal open. Every variable is described in the [inventory configuration table](../ECInventory/README.md#configuration).

## 3. Laravel API

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

Edit `.env`: set `DB_DATABASE=auth_system`, `DB_USERNAME=root`, an empty `DB_PASSWORD`, and `INVENTORY_API_KEY=change-me`. Set `PERFORMANCE_STORE=file` and `CACHE_STORE=file` because there is no Redis. Do not commit this file.

```powershell
php artisan migrate --seed
```

```powershell
php artisan storage:link
```

```powershell
php artisan serve --port=8000
```

The product seeder asks the inventory service to store a quantity for every product, so the Go service must already be running.

Open `http://localhost:8000/api/v3/products`. Demo accounts, password `password`: `admin@example.com` and `user@example.com`.

## 4. Reset all data

Product ids change after a fresh migration, so the inventory must be re-seeded last. In `ECBackend`:

```powershell
php artisan migrate:fresh --seed
```

In `ECInventory`, with the same environment variables as step 2 and the application database name:

```powershell
$env:APP_DB_NAME="auth_system"
```

```powershell
go run ./cmd/seed
```

The output reads `inventory reset and seeded: N products, M units`. Options and behaviour are in [Seeding](../ECInventory/README.md#seeding).

## 5. Performance data set

Use a separate database so the main data stays small. Create `ecommerce_perf`, point `DB_DATABASE` at it, set `API_RATE_LIMIT=100000`, then in `ECBackend`:

```powershell
php artisan migrate --seed
```

```powershell
php artisan perf:seed --products=50000 --orders=200000 --skip-inventory
```

```powershell
$env:APP_DB_NAME="ecommerce_perf"
```

Run `go run ./cmd/seed` in `ECInventory`, then follow [PERFORMANCE.md](../PERFORMANCE.md). To go back to the normal data, restore `DB_DATABASE=auth_system`, run `php artisan config:clear`, and re-seed with `APP_DB_NAME=auth_system`.

## Differences from Docker

| Aspect | Local without Docker | Docker |
| --- | --- | --- |
| Cache store | File, so cache lookups are slower than Redis | Redis |
| Read replica | Not available, so the replica step is skipped | MySQL 8 GTID replica |
| PHP server | Single process, because `PHP_CLI_SERVER_WORKERS` is not supported on Windows | Single process |
| Ports | MySQL 3306 | 3307, 3308, 3309 |
| Reproducibility | Depends on the machine | Same images everywhere, in theory |

## Postman

Import a collection from [`ECBackend/postman`](../ECBackend/postman) and set the base URL to `http://localhost:8000`. Log in once, and the collection stores the token. The flows to try are listed in the [backend README](../ECBackend/README.md#postman-collection).
