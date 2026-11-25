# Docker

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

The compose file starts the whole platform: nginx 1.30.2 in front of three Laravel instances, three queue workers, one stock consumer, Reverb, the web client, RabbitMQ, Redis, two MySQL servers and the Go inventory service. It was started and ran healthy on the author's machine, see [verification-status.md](verification-status.md). How the instances share work is in [scaling.md](scaling.md).

## Start

In PowerShell from `FReq_05`:

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

Pull the load balancer image first if you want to confirm the tag: `docker pull nginx:1.30.2-alpine`. Open `http://localhost:5173`. Demo accounts: `admin@example.com` and `user@example.com`, password `password`.

## First run

1. The first build is slow. The `grpc` PHP extension is compiled from source, which takes 15 to 40 minutes, and Docker may print `[output clipped, log limit 2MiB reached]`, which is only the log being cut. The layer is cached, so later builds take seconds. Avoid `--no-cache`.
2. Images pulled besides the three built ones: nginx 1.30.2, MySQL 8, Redis 7, RabbitMQ 3.13, and `grafana/k6` on the first load test.
3. `migrate` appears as exited with code 0. `docker compose ps` hides it because it has finished. `docker compose ps -a` shows it.
4. If an earlier `ec5_rabbitmq` container from the local set-up exists, remove it with `docker rm -f ec5_rabbitmq`.
5. In PowerShell, run `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass` before `.\loadtest\failover.ps1`.

## Containers

| Container | Role | Port |
| --- | --- | --- |
| `ec5_nginx` | nginx 1.30.2, `least_conn` over the three instances | 8000 |
| `ec5_app1`, `ec5_app2`, `ec5_app3` | Laravel API (`CONTAINER_ROLE=app`), `INSTANCE_NAME=app1` to `app3` | none, reached through nginx |
| `ec5_migrate` | One-shot: migrations, seeding, queue topology, then exits | none |
| `ec5_worker1`, `ec5_worker2`, `ec5_worker3` | `queue:work rabbitmq` for `emails` and `broadcasts` | none |
| `ec5_consumer` | `inventory:consume` | none |
| `ec5_reverb` | Reverb WebSocket server | 8080 |
| `ec5_web` | React client served by nginx | 5173 |
| `ec5_rabbitmq` | RabbitMQ with the management interface | 5672, 15672 |
| `ec5_inventory` | Go gRPC inventory service | 50051 |
| `ec5_db` | API database | 3307 |
| `ec5_inventory_db` | Inventory database | 3308 |
| `ec5_redis` | Cache, cart, locks, rate limiting. AOF persistence, `noeviction` | 6379 |
| `ec5_mailpit` | Mail catcher, profile `mail` | 8025 |

The stack of requirement 4 in [`FReq_04`](../../FReq_04/README.md) and the stack of requirements 2 and 3 in [`FReq_02_03`](../../FReq_02_03/README.md) publish some of the same host ports (`8000`, `50051`, `3307`, `3308`). Run `docker compose down` in one stack before starting another, or change the ports in `.env`, see [configuration.md](configuration.md#docker-compose).

The Laravel image is built once, tagged `ecommerce-backend:req5`, and started as three `app` containers, three `worker` containers, one `consumer` and one `reverb` from the same build. The role comes from `CONTAINER_ROLE`. Only the `migrate` container migrates and seeds, so three instances starting together never race on the schema.

## Start order

`db`, `redis`, `rabbitmq` and `inventory-service` become healthy first. `migrate` then runs once, migrates, seeds, declares the queue topology and exits. `app1`, `app2` and `app3` start after `migrate` completed and pass their health check. `nginx` starts when the three are healthy. `worker1` to `worker3`, `consumer` and `reverb` start after `migrate` as well, and `web` starts when `nginx` is healthy.

## Why Redis is the cache store here

The cart, the locks, the stock event de-duplication and the mail idempotency keys live in the cache. Separate containers need a cache they all see, so the compose file sets `CACHE_STORE=redis`. Redis runs with `--appendonly yes` so data survives a restart, and `--maxmemory-policy noeviction` so a lock or a marker is never evicted. See [scaling.md](scaling.md#problems-met-and-how-each-was-solved).

## Web image build arguments

The React client reads its configuration at build time, so changing a value means rebuilding the image.

| Argument | Compose source |
| --- | --- |
| `VITE_API_URL` | `http://localhost:${APP_PORT}`, which is nginx |
| `VITE_API_MODE` | `${WEB_API_MODE}`, the default mode before the user switches |
| `VITE_REVERB_APP_KEY` | `${REVERB_APP_KEY}` |
| `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | `localhost`, `${REVERB_PORT}`, `http` |

The browser connects to `localhost`, not to the container names, because it runs on the host.

## Profiles

| Profile | Service | Use |
| --- | --- | --- |
| `mail` | `mailpit` | `docker compose --profile mail up` and set `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` |
| `tools` | `inventory-tests` | Go integration tests on the inventory database |
| `tools` | `k6` | Load tests through nginx, see [loadtest](../loadtest/README.md) and [load-testing.md](load-testing.md) |

## Common commands

Stop the platform:

```powershell
docker compose down
```

Reset all data:

```powershell
docker compose down -v
```

Follow the logs of the instances or of the workers (every instance writes to standard error):

```powershell
docker compose logs -f app1 app2 app3
docker compose logs -f worker1 worker2 worker3
```

Stop and start one instance, to see nginx route around it:

```powershell
docker compose stop app2
docker compose start app2
```

Add the number of PHP workers inside each instance (default 4) in `.env`:

```text
PHP_WORKERS=8
```

Run the distribution test and the sync against async comparison:

```powershell
docker compose --profile tools run --rm k6 run /scripts/scale.js
docker compose --profile tools run --rm -e MODE=async -e LABEL=async k6 run /scripts/orders.js
```

## Adding a fourth instance

Copy the `app3` block as `app4` with `INSTANCE_NAME: app4` and `container_name: ec5_app4`, add `server app4:8000 resolve max_fails=3 fail_timeout=10s;` to the upstream in `docker/nginx/nginx.conf`, add `app4` to `depends_on` of `nginx`, and pass `-e INSTANCES=app1,app2,app3,app4` to k6.
