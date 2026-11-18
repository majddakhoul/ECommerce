# Verification status

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

This file separates what was executed from what was only written. The code was written and tested in a Linux container that could not install the RabbitMQ and Reverb packages, could not run Docker, and could not reach a broker. The numbers below are exact for that environment.

## Laravel

| Item | Status | Evidence |
| --- | --- | --- |
| Full test suite | Passed | 175 tests, `php artisan test` |
| Sync and async path registration, mode header | Passed | `ProcessingModeTest` |
| Mail queued in async and inline in sync, idempotency | Passed | `OrderProcessingTest` |
| Realtime events per mutation, nothing in sync, nothing on rollback | Passed | `RealtimeEventsTest` |
| Stock event validation, de-duplication, broadcast | Passed | `StockEventHandlerTest` |
| Private channel authorization | Passed | `BroadcastAuthTest` with a Pusher-signature test double |
| `laravel/reverb`, `php-amqplib`, `pusher-php-server`, `laravel-queue-rabbitmq` installed and used | Not run | The packages could not be downloaded. Configuration was written from their documentation |
| `composer.lock` | Present | Generated on a development machine with `composer update`, after enabling `ext-sockets` |
| `queue:work rabbitmq` against a broker | Not run | |
| `reverb:start` and a real browser connection | Not run | |
| `inventory:consume` against a broker | Not run | Logic tested, the AMQP calls were not |
| `messaging:topology` against a broker | Not run | |

## Go

| Item | Status | Evidence |
| --- | --- | --- |
| Event model, publisher, service publishing, config | Passed | `go test -race` on `internal/events`, `internal/events/rabbitmq`, `internal/service`, `internal/config` |
| AMQP adapter (`broker` package) | Type-checked only | Compiled against a hand-written stub of `amqp091-go`, never against the real library or a broker |
| gRPC interceptor and `main` | Not compiled | The generated gRPC code and dependencies were not available |
| `go.sum` | Present | Generated on a development machine with `go mod tidy` |

## React

| Item | Status | Evidence |
| --- | --- | --- |
| Type check | Passed | `npm run typecheck` with strict mode |
| Unit tests | Passed | 27 tests, `npm test` |
| Production build | Passed | `npm run build` |
| Pages render without console errors | Checked | Headless browser against a mocked API: shop, admin dashboard, admin orders, my orders |
| Against the real API, login, cart, checkout | Not run | |
| Echo connection, private channel authorization, live updates | Not run | Needs Reverb |
| Image upload on product create and update | Not run | Request built to match the API, not exercised |

## Run on a development machine

The following were run by the author on Windows with XAMPP, a local RabbitMQ and Node.js, outside Docker. They are reported as observed, not as automated evidence.

| Item | Observation |
| --- | --- |
| `composer update`, `npm install`, `go mod tidy` | Completed. `composer.lock`, `package-lock.json` and `go.sum` exist |
| Laravel API, Go gRPC service and the order flow | Worked through the web client |
| Reverb start and browser connection | Refused at first with `Origin not allowed` because the allowed origins contained a scheme and a port. Fixed with host names only, see [realtime.md](realtime.md#allowed-origins) |
| Live updates between an administrator window and a customer window | Not yet confirmed after the origin fix |

## Docker

| Item | Status |
| --- | --- |
| `docker-compose.yml` parses and lists the services | Checked |
| `docker compose up --build` | Not executed |
| Images build (PHP gRPC extension, Go build, web build) | Not executed |
| Health checks and start order | Not executed |
| Worker, consumer and Reverb containers | Not executed |

## Requirement 4 behaviours still to be shown on a running stack

| Behaviour | How to show it |
| --- | --- |
| A stopped worker loses no mail | [Runbook 1](reliability.md#runbook-1-a-stopped-worker-loses-nothing) |
| A killed worker returns the message | [Runbook 2](reliability.md#runbook-2-a-killed-worker-returns-the-message) |
| Retries and the failed queue | [Runbook 3](reliability.md#runbook-3-retries-and-the-failed-queue) |
| Dead letter queue for stock events | [Runbook 4](reliability.md#runbook-4-a-poisoned-stock-event-reaches-the-dead-letter-queue) |
| Duplicate suppression | [Runbook 5](reliability.md#runbook-5-duplicates-are-ignored) |
| Broker outage | [Runbook 6](reliability.md#runbook-6-an-unavailable-broker) |
| Sync versus async response time | [`loadtest/orders.js`](../loadtest/README.md) |
| Live updates between two browsers | [Manual checks](testing.md#manual-checks) |
