# Requirement compliance

> Part of the [ECommerce](../../README.md) repository, requirement 5 ([FReq_05](../README.md)).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../../FReq_04/README.md) · [Requirement 5](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Scaling](scaling.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Load testing](load-testing.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

This page maps each point of requirement 4, as understood from the assignment brief, to its implementation and to the evidence for it. The evidence column says whether it was proven by an automated test, by a manual run described in a runbook, or only by reading the code. See [verification-status.md](verification-status.md) for what has been executed.

## Table of contents

- [Requirement 5 matrix](#requirement-5-matrix)
- [Requirement 4 matrix](#requirement-4-matrix)
- [Constraints](#constraints)
- [Where to look](#where-to-look)
- [Known limits](#known-limits)

## Requirement 5 matrix

| # | Requirement | Implementation | Evidence |
| --- | --- | --- | --- |
| 1 | Several instances of the Laravel application | `app1`, `app2`, `app3` from one image | [docker.md](docker.md). Started and healthy, see [verification-status.md](verification-status.md) |
| 2 | Several queue workers | `worker1`, `worker2`, `worker3` on the same queues | [docker.md](docker.md), Postman folder `Requirement 5`, check 11 and 12 |
| 3 | A load balancer, nginx 1.30.2 | `docker/nginx/nginx.conf`, image `nginx:1.30.2-alpine`, policy `least_conn` | [scaling.md](scaling.md#nginx). nginx started and became healthy with this file. `nginx -t` was not run separately |
| 4 | Shared database, inventory service, broker, web client | One of each, used by every instance | `docker-compose.yml` |
| 5 | Shared cache, so jobs are not repeated | Redis, `OnceGuard` | `OnceGuardTest`, `ConcurrentDeliveryTest`, and `loadtest/mail.js` with Mailpit: 51 orders, 102 messages, 0 duplicated, see [load-testing.md](load-testing.md#results-observed) |
| 6 | The cart is visible from any instance | Cart in Redis, one lock per user | `CartConcurrencyTest`, `loadtest/cart.js`, Postman folder `Requirement 5` |
| 7 | Docker setup that builds and runs the images and instances | One-shot `migrate`, health checks, ordered start | `docker compose up --build` ran, all services healthy |
| 8 | Clean code without comments | No comment in the new PHP, JavaScript, shell and YAML files | Review of the diff |
| 9 | k6: 500 requests over 2 minutes, with the distribution, and an instance stopped | `scale.js`, `failover.ps1`, `failover.sh` | [load-testing.md](load-testing.md#results-observed). Executed: 167, 167, 167, and a run with `app2` stopped |
| 10 | Postman collections for requirements 4 and 5 | `Requirement-4-v4`, `Requirement-5-v5` | JSON valid. Not run in Postman |
| 11 | Documentation of requirement 5 and links with requirements 1 to 4 | [scaling.md](scaling.md), [load-testing.md](load-testing.md), the nav lines of every README | Link check over all Markdown files |

## Requirement 4 matrix

| # | Requirement | Implementation | Evidence |
| --- | --- | --- | --- |
| 1 | Two versions of the service, synchronous and asynchronous, of the same functionality | One route file mounted under `/api/v4/sync` and `/api/v4/async`. `SetProcessingMode` and the scoped `ProcessingMode` service decide the side effects | `ProcessingModeTest` |
| 2 | The synchronous version completes work inside the request | Order mails are sent inline by `NotificationService`. No events are dispatched | `OrderProcessingTest` |
| 3 | The asynchronous version moves slow work to queues | `SendOrderMail` on the `emails` queue, events on the `broadcasts` queue, both on RabbitMQ | `OrderProcessingTest`, `RealtimeEventsTest` |
| 4 | RabbitMQ is the message broker | `laravel-queue-rabbitmq` for jobs, `php-amqplib` for the stock consumer, `amqp091-go` in the Go service. Compose runs `rabbitmq:3.13-management` | Code, [docker.md](docker.md). Jobs were consumed by three workers in the Docker stack, see [verification-status.md](verification-status.md) |
| 5 | Retries and failure handling | Job tries and back-off, failed-job queues, manual acknowledgement, dead-letter queue for stock events | [reliability.md](reliability.md) runbooks 1 to 6 |
| 6 | Idempotency | Mail idempotency key, stock `event_id` de-duplication, product and cart events carry full state | `OrderProcessingTest`, `StockEventHandlerTest` |
| 7 | Real-time updates over WebSocket with a ready server | Laravel Reverb, events for products, stock, carts and orders | `RealtimeEventsTest`. Live delivery described in [testing.md](testing.md#manual-checks) |
| 8 | Real-time updates to the right audience | Public channels for catalogue and stock, private channels for orders and carts | `BroadcastAuthTest` |
| 9 | Inventory changes reach clients | Go service publishes `stock.changed`, the consumer broadcasts it | `StockEventHandlerTest`, Go tests of the publisher |
| 10 | A web client that shows the behaviour | React application with a mode switch, live catalogue, cart, orders and an admin dashboard | 27 unit tests, type check, build |
| 11 | The client follows a clean architecture | `Domain`, `Data`, `Presentation` and `Services` folders, with the domain free of framework code | [ECWeb README](../ECWeb/README.md) |
| 12 | Visual identity shared with the Flutter client of requirement 1 | Palette, radii, shadows and component specifications are defined as CSS custom properties in `ECWeb/src/Presentation/Styles/tokens.css` | [ECWeb README](../ECWeb/README.md) |
| 13 | Docker for the whole stack | `docker-compose.yml` with API, worker, consumer, Reverb, web, RabbitMQ, two MySQL databases, Redis and the inventory service | Built and run with requirement 5, all services healthy |
| 14 | Performance comparison of the two versions | k6 script that places orders on both paths | [loadtest README](../loadtest/README.md) |
| 15 | Documentation | This folder, the component READMEs and the Postman collection | Link check over all Markdown files |
| 16 | Earlier requirements keep working | `/api/v1` to `/api/v3` unchanged and tested | `ApiVersionTest` and the rest of the 187 tests |

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

- The Docker images were built and the stack ran for requirement 5. The runbooks of [reliability.md](reliability.md) were not run, and live delivery between two browsers was not confirmed in a recorded run. The checks are in [testing.md](testing.md#manual-checks).
- The web client reuses the Flutter client's visual identity as design tokens. The Flutter application itself belongs to requirement 1 and is not changed here.
- The dashboard is computed in the browser from the latest 100 orders. A reporting endpoint would be needed for larger volumes.
- The cart lives in the application cache, so it is lost when the cache is cleared.
