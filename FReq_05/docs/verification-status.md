# Verification status

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

This file separates what was executed from what was only written, for requirements 4 and 5, which share this code base. The code was first written and tested in a Linux container that could not install the RabbitMQ and Reverb packages, run Docker or reach a broker. The stack was then built and run by the author on Windows with Docker Desktop, and the results of those runs are recorded here. Every run was made once, the figures are not averages and they depend on that machine.

## Laravel

| Item | Status | Evidence |
| --- | --- | --- |
| Full test suite | Passed | 187 tests, 669 assertions, `php vendor/bin/phpunit`, PHP 8.3.6, after `composer install` from `composer.lock` with the platform requirements `ext-grpc`, `ext-pcntl` and `ext-sockets` ignored |
| Sync and async path registration, mode header | Passed | `ProcessingModeTest` |
| Mail queued in async and inline in sync, idempotency | Passed | `OrderProcessingTest` |
| Realtime events per mutation, nothing in sync, nothing on rollback | Passed | `RealtimeEventsTest` |
| Stock event validation, de-duplication, broadcast | Passed | `StockEventHandlerTest` |
| Private channel authorization | Passed | `BroadcastAuthTest` with a Pusher-signature test double |
| Atomic guard, concurrent mail and stock delivery, cart lock, `X-Served-By` | Passed | 12 tests in `tests/Feature/V5`, array cache. Two real processes were not run against each other in PHPUnit. The k6 runs below cover that |
| `laravel/reverb`, `php-amqplib`, `pusher-php-server`, `laravel-queue-rabbitmq` installed | Executed | `composer install` ran inside the Docker build from `composer.lock` |
| `messaging:topology` against a broker | Executed | Run by the `migrate` container. The queues `emails` and `broadcasts` are declared up front, after the workers were seen polling missing queues |
| `queue:work rabbitmq` against a broker | Executed | Three workers consumed the mail jobs of the order runs. 102 mails reached Mailpit through them |
| `reverb:start` | Started | The `reverb` container started. A browser connection and live delivery were not confirmed in a recorded run |
| `inventory:consume` against a broker | Started | The `consumer` container started. No stock event delivery was observed in a recorded run |
| `composer.lock` | Present | Generated on a development machine with `composer update`, after enabling `ext-sockets` |

## Go

| Item | Status | Evidence |
| --- | --- | --- |
| Event model, publisher, service publishing, config | Passed | `go test -race` on `internal/events`, `internal/events/rabbitmq`, `internal/service`, `internal/config` |
| AMQP adapter (`broker` package), gRPC interceptor and `main` | Built | Compiled inside the Docker build against the real libraries. The `inventory` container became healthy and logged `Ping` calls that returned `ok`, and the seeding that depends on it completed |
| `go.sum` | Present | Generated on a development machine with `go mod tidy` |

## React

| Item | Status | Evidence |
| --- | --- | --- |
| Type check | Passed | `npm run typecheck` with strict mode |
| Unit tests | Passed | 27 tests, `npm test` |
| Production build | Passed | `npm run build`, and again inside the Docker build of the `web` image |
| Pages render without console errors | Checked | Headless browser against a mocked API: shop, admin dashboard, admin orders, my orders |
| Against the real API, login, cart, checkout | Not run in a recorded session | The web container started and served on port 5173 |
| Echo connection, private channel authorization, live updates | Not run | Needs a confirmed Reverb session |
| Image upload on product create and update | Not run | Request built to match the API, not exercised |

## Run on a development machine

These were run on Windows with XAMPP, a local RabbitMQ and Node.js, outside Docker, before the Docker runs. They are reported as observed.

| Item | Observation |
| --- | --- |
| `composer update`, `npm install`, `go mod tidy` | Completed. `composer.lock`, `package-lock.json` and `go.sum` exist |
| Laravel API, Go gRPC service and the order flow | Worked through the web client |
| Reverb start and browser connection | Refused at first with `Origin not allowed` because the allowed origins contained a scheme and a port. Fixed with host names only, see [realtime.md](realtime.md#allowed-origins) |
| Live updates between an administrator window and a customer window | Not confirmed after the origin fix |

## Requirement 5

| Item | Status | Evidence |
| --- | --- | --- |
| `docker-compose.yml` starts the stack | Executed | `docker compose up --build` on a Windows machine. All services healthy, `migrate` exited with code 0. The first build is slow because the `grpc` PHP extension is compiled |
| `docker/nginx/nginx.conf` | Executed | nginx started and became healthy. The `resolve` parameter in `upstream` was confirmed in the nginx release notes (open source since 1.27.3) |
| `nginx:1.30.2-alpine` tag | Executed | Pulled and running |
| Three instances, three workers, `migrate` ordering | Executed | `docker compose ps` shows `app1` to `app3` healthy and `worker1` to `worker3` up, `migrate` ran once before them |
| Distribution of 500 requests over 2 minutes | Executed | 167, 167, 167, 0 failed requests of 502, p95 55.2 ms, see [load-testing.md](load-testing.md#results-observed) |
| Distribution of 51 orders over 30 seconds | Executed | 17, 17, 17, 51 of 51 orders created |
| Failover with one instance stopped | Executed | `app2` stopped for 40 seconds. 197, 104, 198 and 1 request without an instance header. Thresholds passed |
| Sync against async response time | Executed | `orders.js` once per mode: median 490 ms against 303 ms, p95 604 ms against 402 ms, see [load-testing.md](load-testing.md#results-observed) |
| Cart consistency across instances | Executed | `cart.js`: 400 reads over the three instances, 0 mismatches. Also tested at code level by `CartConcurrencyTest` |
| No duplicate mail under three workers | Executed | `mail.js` after 51 orders with `MAIL_MAILER=smtp` and Mailpit: 102 messages, 102 distinct, 0 duplicated. Also tested at code level by `ConcurrentDeliveryTest` and `OnceGuardTest` |
| k6 scripts | Executed | `scale.js`, `cart.js`, `failover.ps1` and `mail.js` ran in `grafana/k6:0.54.0` |
| Postman collections | JSON valid | Generated by script. Not run in Postman |
| Redis restart keeps the data | Not executed | |
| A second Docker host | Not executed | |
| Delivery to a real mailbox | Not executed | The audit used Mailpit, a local SMTP catcher |

Items marked Executed were run once. The figures depend on the machine. Items still open: the Redis restart check, the runs on a second Docker host, the Postman runs, and the confirmation of live updates between two browsers.

## Requirement 4 behaviours still to be shown on a running stack

| Behaviour | How to show it |
| --- | --- |
| A stopped worker loses no mail | [Runbook 1](reliability.md#runbook-1-a-stopped-worker-loses-nothing) |
| A killed worker returns the message | [Runbook 2](reliability.md#runbook-2-a-killed-worker-returns-the-message) |
| Retries and the failed queue | [Runbook 3](reliability.md#runbook-3-retries-and-the-failed-queue) |
| Dead letter queue for stock events | [Runbook 4](reliability.md#runbook-4-a-poisoned-stock-event-reaches-the-dead-letter-queue) |
| Duplicate suppression | [Runbook 5](reliability.md#runbook-5-duplicates-are-ignored) |
| Broker outage | [Runbook 6](reliability.md#runbook-6-an-unavailable-broker) |
| Sync versus async response time | Executed once, see [load-testing.md](load-testing.md#results-observed) |
| Live updates between two browsers | [Manual checks](testing.md#manual-checks) |

All results of the five requirements are gathered in the repository page [results](../../docs/results.md).
