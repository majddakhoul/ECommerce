# Requirement compliance

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

This page maps each point of requirement 4, as understood from the assignment brief, to its implementation and to the evidence for it. The evidence column says whether it was proven by an automated test, by a manual run described in a runbook, or only by reading the code. See [verification-status.md](verification-status.md) for what has been executed.

## Table of contents

- [Requirement matrix](#requirement-matrix)
- [Constraints](#constraints)
- [Where to look](#where-to-look)
- [Known limits](#known-limits)

## Requirement matrix

| # | Requirement | Implementation | Evidence |
| --- | --- | --- | --- |
| 1 | Two versions of the service, synchronous and asynchronous, of the same functionality | One route file mounted under `/api/v4/sync` and `/api/v4/async`. `SetProcessingMode` and the scoped `ProcessingMode` service decide the side effects | `ProcessingModeTest` |
| 2 | The synchronous version completes work inside the request | Order mails are sent inline by `NotificationService`. No events are dispatched | `OrderProcessingTest` |
| 3 | The asynchronous version moves slow work to queues | `SendOrderMail` on the `emails` queue, events on the `broadcasts` queue, both on RabbitMQ | `OrderProcessingTest`, `RealtimeEventsTest` |
| 4 | RabbitMQ is the message broker | `laravel-queue-rabbitmq` for jobs, `php-amqplib` for the stock consumer, `amqp091-go` in the Go service. Compose runs `rabbitmq:3.13-management` | Code, [docker.md](docker.md). Broker flows not run in the build environment |
| 5 | Retries and failure handling | Job tries and back-off, failed-job queues, manual acknowledgement, dead-letter queue for stock events | [reliability.md](reliability.md) runbooks 1 to 6 |
| 6 | Idempotency | Mail idempotency key, stock `event_id` de-duplication, product and cart events carry full state | `OrderProcessingTest`, `StockEventHandlerTest` |
| 7 | Real-time updates over WebSocket with a ready server | Laravel Reverb, events for products, stock, carts and orders | `RealtimeEventsTest`. Live delivery described in [testing.md](testing.md#manual-checks) |
| 8 | Real-time updates to the right audience | Public channels for catalogue and stock, private channels for orders and carts | `BroadcastAuthTest` |
| 9 | Inventory changes reach clients | Go service publishes `stock.changed`, the consumer broadcasts it | `StockEventHandlerTest`, Go tests of the publisher |
| 10 | A web client that shows the behaviour | React application with a mode switch, live catalogue, cart, orders and an admin dashboard | 27 unit tests, type check, build |
| 11 | The client follows a clean architecture | `Domain`, `Data`, `Presentation` and `Services` folders, with the domain free of framework code | [ECWeb README](../ECWeb/README.md) |
| 12 | Visual identity shared with the Flutter client of requirement 1 | Palette, radii, shadows and component specifications are defined as CSS custom properties in `ECWeb/src/Presentation/Styles/tokens.css` | [ECWeb README](../ECWeb/README.md) |
| 13 | Docker for the whole stack | `docker-compose.yml` with API, worker, consumer, Reverb, web, RabbitMQ, two MySQL databases, Redis and the inventory service | Compose parsed. Images not built in the build environment |
| 14 | Performance comparison of the two versions | k6 script that places orders on both paths | [loadtest README](../loadtest/README.md) |
| 15 | Documentation | This folder, the component READMEs and the Postman collection | Link check over all Markdown files |
| 16 | Earlier requirements keep working | `/api/v1` to `/api/v3` unchanged and tested | `ApiVersionTest` and the rest of the 175 tests |

## Constraints

| Constraint | How it is met |
| --- | --- |
| Messages must not be lost when a worker stops | Messages stay in a durable RabbitMQ queue until a worker acknowledges them |
| A failing mail server must not fail the order | The mail is sent after the order is stored. In async mode the job is retried, in sync mode the failure is logged |
| A rolled-back transaction must not publish | Events use `afterCommit` |
| A poisoned message must not block the queue | Invalid stock events are rejected without requeue and land in the dead-letter queue |
| The inventory service must not be slowed by the broker | Publishing is buffered with a size limit and confirms, and a stock change never waits for the broker |
| Private data must not leak through WebSockets | Order and cart channels are private and checked against the JWT user |
| Sync and async must be comparable | Same controllers, same validation, same database. Only the side effects differ |
| Secrets must not be committed | `.env` is ignored. Templates contain empty values |

## Where to look

| Question | Document |
| --- | --- |
| How do the parts fit together | [architecture.md](architecture.md) |
| What does an event look like | [realtime.md](realtime.md) |
| What happens when something fails | [reliability.md](reliability.md) |
| Which variable does what | [configuration.md](configuration.md) |
| How do I run it | [local-setup.md](local-setup.md), [docker.md](docker.md) |
| How do I prove it works | [testing.md](testing.md) |
| What has not been executed | [verification-status.md](verification-status.md) |
| Every endpoint | [ECBackend README](../ECBackend/README.md#api-endpoints) |

## Known limits

- The broker flows (worker, consumer, topology, Go publisher) and the Docker images were not executed in the environment where the code was written. Local runs on a development machine showed the REST API, the gRPC link and the order flow working. Live delivery between two browsers still has to be confirmed on a running stack with the checks in [testing.md](testing.md#manual-checks).
- The web client reuses the Flutter client's visual identity as design tokens. The Flutter application itself belongs to requirement 1 and is not changed here.
- The dashboard is computed in the browser from the latest 100 orders. A reporting endpoint would be needed for larger volumes.
- The cart lives in the application cache, so it is lost when the cache is cleared.
