# Local set-up without Docker

> Part of the [ECommerce](../../README.md) repository, requirement 5 ([FReq_05](../README.md)).

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

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../../FReq_04/README.md) · [Requirement 5](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Scaling](scaling.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Load testing](load-testing.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

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
docker run -d --name ec5_rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3.13-management
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

From `FReq_05/ECInventory`:

```powershell
make proto
```

If `make` is not available (the usual case on Windows), install `protoc` (for example `winget install protobuf`) and the two Go generators, make sure `%USERPROFILE%\go\bin` is on the `PATH`, and run the commands that `make proto` would run:

```powershell
go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.36.6
go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.5.1
New-Item -ItemType Directory -Force gen/inventorypb
protoc --proto_path=../proto --go_out=gen/inventorypb --go_opt=paths=source_relative --go-grpc_out=gen/inventorypb --go-grpc_opt=paths=source_relative ../proto/inventory.proto
```

Then:

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

From `FReq_05/ECBackend`:

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
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `auth_system` (or any database that exists, for example `ecommerce`), `root`, empty |
| `CACHE_STORE`, `PERFORMANCE_STORE` | `file` |
| `QUEUE_CONNECTION` | `rabbitmq` |
| `BROADCAST_CONNECTION` | `reverb` |
| `REVERB_APP_KEY`, `REVERB_APP_SECRET` | any random strings |
| `INVENTORY_API_KEY` | `change-me`, the same as the Go service |

The inventory service must be running before this step, because `ProductSeeder` sets the stock of the demo products through it.

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

From `FReq_05/ECWeb`:

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

## Several instances without Docker

To test requirement 5 on one machine without Docker, start several copies of the API on different ports and put nginx in front.

1. Use a cache store that every copy shares, and make sure it supports atomic locks. `CACHE_STORE=database` (run `php artisan migrate`, which creates the `cache` and `cache_locks` tables) or `redis`. The `file` store also works while every copy runs from the same `ECBackend` folder on one machine, because the files are shared, but it does not scale beyond that machine.
2. Give every copy the same `.env`, so the same `APP_KEY` and `JWT_SECRET`, and a different `INSTANCE_NAME`.
3. Start the copies, one terminal each:

```powershell
$env:INSTANCE_NAME="app1"; php artisan serve --port=8001
$env:INSTANCE_NAME="app2"; php artisan serve --port=8002
$env:INSTANCE_NAME="app3"; php artisan serve --port=8003
```

4. Start one or more workers with the same command as in step 5. They share the queue, so the lock described in [scaling.md](scaling.md#why-a-lock-and-why-it-must-be-atomic) is what prevents a duplicate mail.
5. Use an nginx configuration like the one below. `listen` must not be 8080, which Reverb uses. Use 8000.

```nginx
worker_processes 1;

events {
    worker_connections 1024;
}

http {
    upstream laravel_backend {
        least_conn;
        server 127.0.0.1:8001;
        server 127.0.0.1:8002;
        server 127.0.0.1:8003;
    }

    server {
        listen 8000;

        location / {
            proxy_pass http://laravel_backend;
            proxy_set_header Host $http_host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
    }
}
```

The `Host $http_host` line keeps the port the client used. With `Host $host` the port is dropped. Point the web client and `APP_URL` at `http://localhost:8000`, then run `loadtest/scale.js` with `-e BASE_URL=http://localhost:8000` on the host. `php artisan serve` handles one request at a time unless `PHP_CLI_SERVER_WORKERS` is set (and it needs `--no-reload`), so also set `$env:PHP_CLI_SERVER_WORKERS=4` and add `--no-reload`.

## Differences from Docker

| Aspect | Local | Docker |
| --- | --- | --- |
| Cache store | File, or database or Redis for several instances | Redis with persistence and no eviction |
| Instances | One, or several started by hand behind your own nginx | Three behind nginx 1.30.2, workers three |
| Processes | Started by hand in terminals | One container per role, `migrate` runs once |
| Mail | `log` driver, read `storage/logs/laravel.log` | `log` driver, or Mailpit with the `mail` profile |
