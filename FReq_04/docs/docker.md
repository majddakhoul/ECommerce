# Docker

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

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

The compose file starts the whole platform. It was written and reviewed, and it has not been started, see [verification-status.md](verification-status.md).

## Start

In PowerShell from `FReq_04`:

```powershell
Copy-Item .env.example .env
```

Fill in the empty values in `.env`. Generate each secret with:

```powershell
docker run --rm php:8.3-cli php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

| Variable | Value |
| --- | --- |
| `APP_KEY` | `base64:` followed by a generated value |
| `JWT_SECRET` | a generated value |
| `REVERB_APP_KEY` | any random string. The web image is built with it |
| `REVERB_APP_SECRET` | any random string |

```powershell
docker compose up --build
```

Open `http://localhost:5173`. Demo accounts: `admin@example.com` and `user@example.com`, password `password`.

## Containers

| Container | Role | Port |
| --- | --- | --- |
| `ec4_web` | React client served by nginx | 5173 |
| `ec4_app` | Laravel API (`CONTAINER_ROLE=app`) | 8000 |
| `ec4_worker` | `queue:work rabbitmq` for `emails` and `broadcasts` | none |
| `ec4_consumer` | `inventory:consume` | none |
| `ec4_reverb` | Reverb WebSocket server | 8080 |
| `ec4_rabbitmq` | RabbitMQ with the management interface | 5672, 15672 |
| `ec4_inventory` | Go gRPC inventory service | 50051 |
| `ec4_db` | API database | 3307 |
| `ec4_inventory_db` | Inventory database | 3308 |
| `ec4_redis` | Cache shared by the API, worker and consumer | 6379 |
| `ec4_mailpit` | Mail catcher, profile `mail` | 8025 |

The stack of requirements 2 and 3 in [`FReq_02_03`](../../FReq_02_03/README.md) publishes some of the same host ports (`8000`, `50051`, `3307`, `3308`). Run `docker compose down` in one stack before starting the other, or change the ports in `.env`, see [configuration.md](configuration.md#docker-compose).

The Laravel image is built once and started four times with a different `CONTAINER_ROLE`. The entrypoint runs migrations, seeding and `messaging:topology` only for the `app` role. The other roles wait until the `app` health check passes.

## Start order

`db`, `redis`, `rabbitmq` and `inventory-service` become healthy first, then `app` migrates, seeds and declares the topology, then `worker`, `consumer` and `web` start.

## Why Redis is the cache store here

The cart, the stock event de-duplication and the mail idempotency keys live in the cache. Separate containers need a cache they all see, so the compose file sets `CACHE_STORE=redis`. The file store works only when every process runs on one machine, as in the [local set-up](local-setup.md).

## Web image build arguments

The React client reads its configuration at build time, so changing a value means rebuilding the image.

| Argument | Compose source |
| --- | --- |
| `VITE_API_URL` | `http://localhost:${APP_PORT}` |
| `VITE_API_MODE` | `${WEB_API_MODE}`, the default mode before the user switches |
| `VITE_REVERB_APP_KEY` | `${REVERB_APP_KEY}` |
| `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | `localhost`, `${REVERB_PORT}`, `http` |

The browser connects to `localhost`, not to the container names, because it runs on the host.

## Profiles

| Profile | Service | Use |
| --- | --- | --- |
| `mail` | `mailpit` | `docker compose --profile mail up` and set `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` |
| `tools` | `inventory-tests` | Go integration tests on the inventory database |
| `tools` | `k6` | Load tests, see [loadtest](../loadtest/README.md) |

## Common commands

Stop the platform:

```powershell
docker compose down
```

Reset all data:

```powershell
docker compose down -v
```

Follow the logs of one role:

```powershell
docker compose logs -f worker
```

Run the load test for one mode:

```powershell
docker compose run --rm -e MODE=async -e LABEL=async k6 run /scripts/orders.js
```
