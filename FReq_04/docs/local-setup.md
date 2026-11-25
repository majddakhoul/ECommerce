# Local set-up without Docker

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md) · [Requirement 5](../../FReq_05/README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

This set-up runs every process on one machine. It uses the file cache, which all local processes share.

## Prerequisites

| Tool | Version | Used for |
| --- | --- | --- |
| PHP | 8.2 or newer, with `pdo_mysql`, `mbstring`, `zip`, `sockets` and the `grpc` extension | Laravel API |
| Composer | 2 | PHP dependencies |
| MySQL or MariaDB | 8 recommended | Both databases |
| Go | 1.24 | Inventory service |
| `protoc`, `protoc-gen-go`, `protoc-gen-go-grpc` | see the [Dockerfile](../ECInventory/Dockerfile) | Generating Go code |
| Node.js | 20 or newer | React client |
| RabbitMQ | 3.13 or newer | Queue broker |

Enable `extension=sockets` in `php.ini`. Without it the RabbitMQ packages cannot connect.

## 1. RabbitMQ

With Docker, one command:

```powershell
docker run -d --name ec4_rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3.13-management
```

Without Docker on Windows, install Erlang/OTP and then RabbitMQ from the official installers, then enable the management plugin from the RabbitMQ sbin folder:

```powershell
rabbitmq-plugins enable rabbitmq_management
```

Check `http://localhost:15672` (user and password `guest`).

## 2. Databases

```powershell
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS inventory CHARACTER SET utf8mb4;"
```

## 3. Inventory service

From `FReq_04/ECInventory`:

```powershell
make proto
```

If `make` is not available, run the `protoc` command from the [Makefile](../ECInventory/Makefile) by hand. Then:

```powershell
go mod tidy
```

```powershell
$env:DB_USER="root"; $env:DB_PASSWORD=""; $env:DB_NAME="inventory"; $env:INVENTORY_API_KEY="change-me"; $env:RABBITMQ_HOST="127.0.0.1"
```

```powershell
go run ./cmd/server
```

Without `RABBITMQ_HOST` the service runs and publishes nothing. Every variable is in the [inventory configuration table](../ECInventory/README.md#configuration).

## 4. Laravel API

From `FReq_04/ECBackend`:

```powershell
composer install
```

`composer.lock` is committed, so `composer install` installs the exact versions that were used. The PHP extensions `sockets` (needed by `php-amqplib`) and `grpc` must be enabled. In XAMPP remove the semicolon in front of `extension=sockets` in `php.ini`, then restart the shell.

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

```powershell
php artisan jwt:secret
```

Edit `.env`:

| Variable | Value |
| --- | --- |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `auth_system`, `root`, empty |
| `CACHE_STORE`, `PERFORMANCE_STORE` | `file` |
| `QUEUE_CONNECTION` | `rabbitmq` |
| `BROADCAST_CONNECTION` | `reverb` |
| `REVERB_APP_KEY`, `REVERB_APP_SECRET` | any random strings |
| `INVENTORY_API_KEY` | `change-me`, the same as the Go service |

```powershell
php artisan migrate --seed
```

```powershell
php artisan storage:link
```

```powershell
php artisan messaging:topology
```

## 5. Start the four Laravel processes

Each command needs its own terminal, started from `ECBackend`:

```powershell
php artisan serve --port=8000
```

```powershell
php artisan reverb:start
```

```powershell
php artisan queue:work rabbitmq --queue=emails,broadcasts --tries=3
```

```powershell
php artisan inventory:consume
```

`composer dev` starts the server, the worker, Reverb and the consumer together.

## 6. React client

From `FReq_04/ECWeb`:

```powershell
npm install
```

```powershell
Copy-Item .env.example .env
```

Set `VITE_REVERB_APP_KEY` to the same value as `REVERB_APP_KEY` in the backend. Then:

```powershell
npm run dev
```

Open `http://localhost:5173`. The profile page has the sync/async switch.

## 7. Reset all data

In `ECBackend`:

```powershell
php artisan migrate:fresh --seed
```

Product ids change after a fresh migration, so re-seed the inventory afterwards from `ECInventory`:

```powershell
$env:APP_DB_NAME="auth_system"
```

```powershell
go run ./cmd/seed
```

## Differences from Docker

| Aspect | Local | Docker |
| --- | --- | --- |
| Cache store | File | Redis |
| Processes | Started by hand in terminals | One container per role |
| Mail | `log` driver, read `storage/logs/laravel.log` | `log` driver, or Mailpit with the `mail` profile |
