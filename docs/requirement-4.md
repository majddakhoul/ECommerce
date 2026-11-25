# Requirement 4: queues and real-time updates

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

Requirement 4 lives in [FReq_04](../FReq_04/README.md). It adds asynchronous work and live updates on top of [requirements 2 and 3](requirement-2-3.md): order mail and broadcasts become RabbitMQ jobs with retries, the Go service publishes `stock.changed` events, Laravel Reverb pushes changes to browsers over WebSocket, and a React web client with an administrator dashboard consumes every endpoint.

## What it contains

| Part | Folder | Documentation |
| --- | --- | --- |
| Laravel API with `/api/v4/sync` and `/api/v4/async` over the same controllers | [ECBackend](../FReq_04/ECBackend/README.md) | [Backend README](../FReq_04/ECBackend/README.md) |
| Go inventory service that publishes stock events | [ECInventory](../FReq_04/ECInventory/README.md) | [Inventory README](../FReq_04/ECInventory/README.md) |
| React web client | [ECWeb](../FReq_04/ECWeb/README.md) | [Web README](../FReq_04/ECWeb/README.md) |
| gRPC and event contract | [proto](../FReq_04/proto/README.md) | [Contract](../FReq_04/proto/README.md) |
| k6 script `orders.js` | [loadtest](../FReq_04/loadtest/README.md) | [Load test guide](../FReq_04/loadtest/README.md) |
| Cross-cutting documents | [docs](../FReq_04/docs/README.md) | [Architecture](../FReq_04/docs/architecture.md), [Realtime](../FReq_04/docs/realtime.md), [Reliability](../FReq_04/docs/reliability.md), [Configuration](../FReq_04/docs/configuration.md), [Docker](../FReq_04/docs/docker.md), [Local set-up](../FReq_04/docs/local-setup.md), [Testing](../FReq_04/docs/testing.md), [Security](../FReq_04/docs/security.md), [Compliance](../FReq_04/docs/compliance.md), [Troubleshooting](../FReq_04/docs/troubleshooting.md), [Verification status](../FReq_04/docs/verification-status.md) |

## Prerequisites

| Way | Tools |
| --- | --- |
| Docker | Git, Docker Desktop, ports 5173, 8000, 8080, 5672, 15672, 50051, 3307, 3308 and 6379 free |
| Local | Git, PHP 8.2 or newer with `grpc` and `sockets`, Composer, MySQL, Go 1.24, `protoc`, Node.js 20 or newer, RabbitMQ 3.13 |

Install them with [environment-setup.md](environment-setup.md).

## Run with Docker

From `FReq_04`:

```powershell
Copy-Item .env.example .env
```

Fill the empty values of `.env`. Generate each secret with:

```powershell
docker run --rm php:8.3-cli php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

| Variable | Value |
| --- | --- |
| `APP_KEY` | `base64:` followed by a generated value |
| `JWT_SECRET` | A generated value |
| `REVERB_APP_KEY` | Any random string. The web image is built with it |
| `REVERB_APP_SECRET` | Any random string |

```powershell
docker compose up --build
```

Open `http://localhost:5173`. The demo accounts are `admin@example.com` and `user@example.com`, with the password `password`. RabbitMQ is on `http://localhost:15672` (`guest` and `guest`). The container table and the start order are in [docker.md](../FReq_04/docs/docker.md).

Stop with `docker compose down`, and remove the data with `docker compose down -v`.

## Run locally

Five things run at once, each in its own terminal. The full steps with every variable are in [local-setup.md](../FReq_04/docs/local-setup.md). In short:

1. Start RabbitMQ, for example `docker run -d --name ec_rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3.13-management`.
2. Create the databases `auth_system` and `inventory`.
3. In `FReq_04/ECInventory`: generate the gRPC code (see [requirement-2-3.md](requirement-2-3.md#inventory-service)), then set `RABBITMQ_HOST` and run `go run ./cmd/server`.
4. In `FReq_04/ECBackend`: `composer install`, copy `.env.example` to `.env`, `php artisan key:generate`, `php artisan jwt:secret`, set `QUEUE_CONNECTION=rabbitmq`, `BROADCAST_CONNECTION=reverb`, the two Reverb values and `INVENTORY_API_KEY`, then `php artisan migrate --seed`, `php artisan storage:link` and `php artisan messaging:topology`.
5. Start the four Laravel processes, one terminal each, from `FReq_04/ECBackend`:

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

6. In `FReq_04/ECWeb`: `npm install`, copy `.env.example` to `.env`, set `VITE_REVERB_APP_KEY` to the same value as the backend, then `npm run dev` and open `http://localhost:5173`.

Reverb accepts a browser only when its origin is allowed. `REVERB_ALLOWED_ORIGINS` holds host names without a scheme or a port, for example `localhost,127.0.0.1`, see [realtime.md](../FReq_04/docs/realtime.md#allowed-origins).

## Sync and async

The web client has a switch on the profile page. In `sync` mode the order request sends the mail itself and no live updates run, so the profile page shows "Live updates off", which is the expected state of that mode and not a fault. In `async` mode the mail and the broadcasts are queue jobs and the pill reads "Live".

| Path | Mail | Events |
| --- | --- | --- |
| `/api/v4/sync/...` | Sent inside the request | None |
| `/api/v4/async/...` | A job on the `emails` queue | Published to Reverb through the `broadcasts` queue |

## Test

From `FReq_04/ECBackend`:

```powershell
php artisan test
```

The suite has 175 tests. From `FReq_04/ECInventory`:

```powershell
go test -race ./internal/...
```

From `FReq_04/ECWeb`:

```powershell
npm test
```

```powershell
npm run typecheck
```

```powershell
npm run build
```

The test table is in [testing.md](../FReq_04/docs/testing.md).

## Measure sync against async

From `FReq_04`, once per mode, with the same settings:

```powershell
docker compose run --rm -e MODE=sync -e LABEL=sync k6 run /scripts/orders.js
```

```powershell
docker compose run --rm -e MODE=async -e LABEL=async k6 run /scripts/orders.js
```

The results are written to `loadtest/results`. The comparison was run once in the requirement 5 stack: the async path had a median of 303 ms against 490 ms for sync, see [results.md](results.md#requirement-5). With `MAIL_MAILER=log` the mail itself is almost free, so use an SMTP server with a real delay to see the full effect of moving it to a queue.

## Prove the reliability

Six runbooks in [reliability.md](../FReq_04/docs/reliability.md) each show one guarantee on the running stack.

| Runbook | Shows |
| --- | --- |
| [1](../FReq_04/docs/reliability.md#runbook-1-a-stopped-worker-loses-nothing) | A stopped worker loses no mail |
| [2](../FReq_04/docs/reliability.md#runbook-2-a-killed-worker-returns-the-message) | A killed worker returns the message |
| [3](../FReq_04/docs/reliability.md#runbook-3-retries-and-the-failed-queue) | Retries and the failed queue |
| [4](../FReq_04/docs/reliability.md#runbook-4-a-poisoned-stock-event-reaches-the-dead-letter-queue) | A poisoned event reaches the dead letter queue |
| [5](../FReq_04/docs/reliability.md#runbook-5-duplicates-are-ignored) | Duplicates are ignored |
| [6](../FReq_04/docs/reliability.md#runbook-6-an-unavailable-broker) | An unavailable broker |

## Postman

Import [`Requirement-4-v4.postman_collection.json`](../FReq_04/ECBackend/postman/Requirement-4-v4.postman_collection.json) from `FReq_04/ECBackend/postman`. It has 52 requests: the earlier flows through the mode paths, broadcast authorization as an administrator and as a user, and checks of the RabbitMQ queues through the management API. It was generated by a script and validated as JSON. It has not been run in Postman, see [results.md](results.md#not-demonstrated).

## Results

The test counts, the real-world observations and the items that were never run are in [results.md](results.md#requirement-4). The documentation of requirement 4 records no Docker run of its own stack. The same Laravel, Go and React code did run inside the requirement 5 stack, whose results are in [results.md](results.md#requirement-5).

## Continue

[Requirement 5](requirement-5.md) runs this same platform as three instances behind a load balancer.
