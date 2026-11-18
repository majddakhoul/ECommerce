# Configuration reference

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

Every setting of requirement 4 in one place. Files named `.env.example` are templates: copy them to `.env` and fill in the secrets. Real `.env` files are ignored by Git and must never be committed.

## Table of contents

- [Where each file is used](#where-each-file-is-used)
- [ECBackend](#ecbackend)
- [ECInventory](#ecinventory)
- [ECWeb](#ecweb)
- [Docker Compose](#docker-compose)
- [Values that must match](#values-that-must-match)
- [Secrets](#secrets)

## Where each file is used

| File | Used by | When |
| --- | --- | --- |
| `ECBackend/.env` | Laravel, the worker, the consumer, Reverb | Running without Docker |
| `ECInventory` environment | The Go service | Variables set in the shell or in compose. The service has no `.env` loader |
| `ECWeb/.env` | Vite | Running `npm run dev` without Docker |
| `FReq_04/.env` | `docker-compose.yml` | Running `docker compose up` |

## ECBackend

### Application and security

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_KEY` | empty | Encryption key, generate with `php artisan key:generate` |
| `JWT_SECRET` | empty | Token signing key, generate with `php artisan jwt:secret` |
| `JWT_TTL` | `60` | Access token lifetime in minutes |
| `JWT_REFRESH_TTL` | `20160` | Refresh window in minutes |
| `APP_DEBUG` | `true` | Must be `false` outside local development |
| `LOGIN_MAX_ATTEMPTS` | `5` | Failed logins before a lock |
| `LOGIN_LOCKOUT_MINUTES` | `15` | Lock duration |
| `API_RATE_LIMIT` | `120` | Requests per minute per user or IP. The Docker file raises it for load tests |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173,http://127.0.0.1:5173` | Full origins allowed to call the REST API |

### Database and cache

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Database driver |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | Database address |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `auth_system`, `root`, empty | Credentials |
| `CACHE_STORE` | `database` in the template | Use `file` for a single machine. Docker uses `redis` so that every container shares the cart and the idempotency keys |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | `127.0.0.1`, `6379`, `null` | Used when the cache is Redis |

### Queue and RabbitMQ

| Variable | Default | Purpose |
| --- | --- | --- |
| `QUEUE_CONNECTION` | `rabbitmq` | Queue driver |
| `RABBITMQ_HOST`, `RABBITMQ_PORT` | `127.0.0.1`, `5672` | Broker address |
| `RABBITMQ_USER`, `RABBITMQ_PASSWORD` | `guest`, `guest` | Broker credentials. `guest` works from localhost only |
| `RABBITMQ_VHOST` | `/` | Virtual host |
| `RABBITMQ_QUEUE` | `default` | Default queue name |
| `QUEUE_EMAILS`, `QUEUE_BROADCASTS` | `emails`, `broadcasts` | Names of the job queues |
| `RABBITMQ_STOCK_PREFETCH` | `20` | Unacknowledged messages per consumer |
| `RABBITMQ_STOCK_MAX_ATTEMPTS` | `3` | Tries before a stock message is dead-lettered |
| `RABBITMQ_STOCK_DEDUPE_TTL` | `3600` | Seconds an `event_id` is remembered |

The exchange, queue and routing key names of the stock flow are defined in `config/messaging.php`. They can be overridden with `RABBITMQ_EVENTS_EXCHANGE`, `RABBITMQ_STOCK_ROUTING_KEY`, `RABBITMQ_STOCK_QUEUE`, `RABBITMQ_STOCK_DLX`, `RABBITMQ_STOCK_DEAD_KEY` and `RABBITMQ_STOCK_DLQ`, and the defaults equal those of the Go service. The failed-job exchange is `RABBITMQ_FAILED_EXCHANGE` (`ec.jobs.failed`).

### Reverb

| Variable | Default | Purpose |
| --- | --- | --- |
| `BROADCAST_CONNECTION` | `reverb` | Broadcast driver |
| `REVERB_APP_ID` | `ecommerce` | Application id |
| `REVERB_APP_KEY` | empty | Public key, also given to the browser |
| `REVERB_APP_SECRET` | empty | Secret used to sign private channels. Never send it to the browser |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | `localhost`, `8080`, `http` | Address the API uses to publish |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT` | `0.0.0.0`, `8080` | Address the server binds to |
| `REVERB_ALLOWED_ORIGINS` | `localhost,127.0.0.1` | Host names allowed to connect. No scheme, no port |

### Inventory gateway

| Variable | Default | Purpose |
| --- | --- | --- |
| `INVENTORY_GRPC_HOST`, `INVENTORY_GRPC_PORT` | `127.0.0.1`, `50051` | Address of the Go service |
| `INVENTORY_API_KEY` | `change-me` | Shared key sent as gRPC metadata. Must equal the Go service value |
| `INVENTORY_GRPC_TIMEOUT` | `2` | Deadline in seconds for each call |

### Mail

| Variable | Default | Purpose |
| --- | --- | --- |
| `MAIL_MAILER` | `log` | `log` writes mails to the log, `smtp` sends them |
| `MAIL_HOST`, `MAIL_PORT` | `127.0.0.1`, `2525` | SMTP server. Gmail uses `smtp.gmail.com` and `587` |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | `null` | SMTP credentials. Gmail needs an App Password |
| `MAIL_SCHEME` | `null` | `smtp` for STARTTLS on 587 |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `hello@example.com`, app name | Sender |

## ECInventory

| Variable | Default | Purpose |
| --- | --- | --- |
| `GRPC_HOST`, `GRPC_PORT` | `0.0.0.0`, `50051` | gRPC listen address |
| `INVENTORY_API_KEY` | empty | Required key. Calls without it are rejected |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | Inventory database |
| `DB_USER`, `DB_PASSWORD`, `DB_NAME` | `inventory`, empty, `inventory` | Inventory database credentials |
| `APP_DB_HOST`, `APP_DB_PORT` | `127.0.0.1`, `3306` | Application database, read once to import existing stock |
| `APP_DB_USER`, `APP_DB_PASSWORD`, `APP_DB_NAME` | `root`, empty, `auth_system` | Application database credentials |
| `DB_MAX_OPEN_CONNS`, `DB_MAX_IDLE_CONNS` | `25`, `10` | Connection pool |
| `DB_CONN_MAX_LIFETIME_SECONDS` | `300` | Connection lifetime |
| `DB_MIGRATE_ATTEMPTS`, `DB_MIGRATE_DELAY_SECONDS` | `30`, `2` | Wait for the database at start |
| `SHUTDOWN_TIMEOUT_SECONDS` | `10` | Graceful stop |
| `RABBITMQ_HOST` | empty | When empty, event publishing is disabled |
| `RABBITMQ_PORT`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_VHOST` | `5672`, `guest`, `guest`, `/` | Broker connection |
| `RABBITMQ_EVENTS_EXCHANGE` | `ec.events` | Topic exchange |
| `RABBITMQ_STOCK_ROUTING_KEY` | `stock.changed` | Routing key |
| `RABBITMQ_STOCK_QUEUE` | `ec.stock.changed` | Queue read by Laravel |
| `RABBITMQ_STOCK_DLX`, `RABBITMQ_STOCK_DEAD_KEY`, `RABBITMQ_STOCK_DLQ` | `ec.events.dlx`, `stock.changed.dead`, `ec.stock.changed.dlq` | Dead-letter topology |
| `EVENT_BUFFER` | `1024` | In-memory buffer while the broker is unreachable. When the buffer is full the new event is dropped and logged, and the stock change itself is never blocked |

## ECWeb

| Variable | Default | Purpose |
| --- | --- | --- |
| `VITE_API_URL` | `http://localhost:8000` | API origin without a path |
| `VITE_API_MODE` | `async` | Initial mode, `sync` or `async`. A choice made in the interface is stored in `localStorage` and wins |
| `VITE_REVERB_APP_KEY` | empty | Must equal `REVERB_APP_KEY`. Empty disables realtime |
| `VITE_REVERB_HOST` | `localhost` | Reverb host seen by the browser |
| `VITE_REVERB_PORT` | `8080` | Reverb port seen by the browser |
| `VITE_REVERB_SCHEME` | `http` | `https` for secure sockets |

Vite reads these at build time. Restart `npm run dev` after editing, and rebuild the image after editing for Docker.

## Docker Compose

`FReq_04/.env.example` lists the variables that compose reads. Everything else is set inside `docker-compose.yml`.

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_KEY`, `JWT_SECRET` | empty, required | Application secrets |
| `INVENTORY_API_KEY` | `change-me` | Shared with the Go service |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | `ecommerce`, required, required | Reverb credentials. The key is also passed to the web build |
| `RABBITMQ_USER`, `RABBITMQ_PASSWORD` | `guest`, `guest` | Broker credentials |
| `APP_PORT` | `8000` | API on the host |
| `WEB_PORT` | `5173` | Web client on the host |
| `WEB_API_MODE` | `async` | Initial mode of the web build |
| `REVERB_PORT` | `8080` | WebSocket server on the host |
| `RABBITMQ_PORT`, `RABBITMQ_UI_PORT` | `5672`, `15672` | Broker and management UI |
| `INVENTORY_PORT` | `50051` | gRPC on the host |
| `ECOMMERCE_DB_PORT`, `INVENTORY_DB_PORT` | `3307`, `3308` | Databases on the host |
| `REDIS_PORT` | `6379` | Redis on the host |
| `API_RATE_LIMIT` | `1000000` | Rate limit raised for the load test |
| `MAIL_MAILER` | `log` | Mail driver. The `mail` profile adds Mailpit on `8025` |

The Requirement 2 and 3 stack in [`FReq_02_03`](../../FReq_02_03/README.md) uses overlapping host ports (`8000`, `50051`, `3307`, `3308`). Stop one stack before starting the other, or change the ports in `.env`.

## Values that must match

| Value | Appears in | Rule |
| --- | --- | --- |
| Reverb key | `REVERB_APP_KEY` and `VITE_REVERB_APP_KEY` | Identical |
| Inventory API key | `INVENTORY_API_KEY` of the API and of the Go service | Identical |
| Broker names | `config/messaging.php` and the Go `RABBITMQ_*` names | Identical. Change both or neither |
| Cache | Every Laravel process | Same store, otherwise the cart and the mail idempotency keys are not shared |
| Allowed origins | `REVERB_ALLOWED_ORIGINS` | Host names that contain the browser's address |
| CORS | `CORS_ALLOWED_ORIGINS` | Full origin of the web client |
| Database | `DB_*` of the API and `APP_DB_*` of the Go service | Same schema when the Go service imports initial stock |

## Secrets

- `APP_KEY`, `JWT_SECRET`, `REVERB_APP_SECRET`, `MAIL_PASSWORD` and `INVENTORY_API_KEY` are secrets. Generate new values per environment.
- A value that was ever pasted into a chat, an issue or a commit is compromised. Rotate it: new application key, new JWT secret, new Gmail App Password.
- `.gitignore` excludes `.env` and `.env.*` except `.env.example`. Check `git status` before every commit.
