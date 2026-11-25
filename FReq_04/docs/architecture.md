# Architecture

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

## Contents

- [Goals and constraints](#goals-and-constraints)
- [Components](#components)
- [Two paths, one code base](#two-paths-one-code-base)
- [Asynchronous work with RabbitMQ](#asynchronous-work-with-rabbitmq)
- [Real-time with Reverb](#real-time-with-reverb)
- [The stock event path](#the-stock-event-path)
- [Channels and events](#channels-and-events)
- [Messaging topology](#messaging-topology)
- [Design decisions](#design-decisions)
- [Known limits](#known-limits)

---

## Goals and constraints

| Constraint | How it is met |
| --- | --- |
| RabbitMQ is mandatory | All queued work runs through the RabbitMQ queue connection, and the Go service publishes to a RabbitMQ exchange |
| The WebSocket server must be a ready product | Laravel Reverb, started with `php artisan reverb:start`. No WebSocket server was written |
| Same API, with and without queues and real-time | `/api/v4/sync/...` and `/api/v4/async/...` use the same controllers, validation and response shapes |
| The web client uses every existing API | Authentication, authorization, products, cart, orders, users and roles are all used by the React client |
| Live products, stock, cart and orders | Reverb channels, see [Channels and events](#channels-and-events) |

---

## Components

```text
React (Laravel Echo) <--WebSocket-- Reverb <--HTTP publish-- Laravel API / consumer
                                                     ^
Laravel API --jobs--> RabbitMQ --> queue worker      |
      |                   ^                          |
      | gRPC              | stock.changed            |
      v                   |                          |
Go inventory service -----+      inventory:consume --+
```

| Component | Responsibility |
| --- | --- |
| `ECBackend` | REST API, queued jobs, event publishing to Reverb, the stock consumer command |
| `ECInventory` | Owns stock. Publishes `stock.changed` to RabbitMQ when a quantity changes |
| RabbitMQ | Durable queues for jobs, the stock event queue, dead letter queues |
| Reverb | WebSocket server. Pushes events to browsers |
| `ECWeb` | React client with a sync/async mode switch and live cache updates |

---

## Two paths, one code base

The routes are registered once, inside one closure, and mounted twice:

| Path | Middleware | Behaviour |
| --- | --- | --- |
| `/api/v4/sync/...` | `processing:sync` | Mails are sent inline. No event is published |
| `/api/v4/async/...` | `processing:async` | Mails are queued. Realtime events are published. The inventory service is told to publish stock events |

`SetProcessingMode` stores the mode in the scoped `ProcessingMode` service. Services ask that object, so no controller knows which path it serves. Every response carries `X-Processing-Mode`. The paths `/api/v1` to `/api/v3` are untouched and stay synchronous.

The v4 catalog does not use the v3 cache. The cache of requirement 3 is an optimization for a read-heavy path, and a cached stock value would hide exactly the changes that requirement 4 makes visible.

---

## Asynchronous work with RabbitMQ

| Work | Queue | Retry |
| --- | --- | --- |
| Order mails (`SendOrderMail`) | `emails` | 5 tries, back-off 10, 60, 300, 900 seconds |
| Realtime events (`ShouldBroadcast`) | `broadcasts` | 3 tries, back-off 2 and 10 seconds |
| `StockChanged` | none, broadcast immediately by the consumer | handled by the consumer retry, see below |

Mail jobs carry an idempotency key stored in the cache (`mail-sent:*`). It is written after a successful delivery, so a job redelivered after its acknowledgement was lost does not send the same mail again. A crash between the delivery and the write can still send a duplicate, which is the usual at-least-once trade-off.

In sync mode the same mail is sent inline, so a slow SMTP server makes the response slow. In async mode the response returns once the job is on the queue. The k6 script in [loadtest](../loadtest/README.md) measures the difference.

---

## Real-time with Reverb

The API publishes to Reverb over HTTP using the Pusher protocol. Browsers connect to Reverb with Laravel Echo. Public channels need no authentication. Private channels call `/api/broadcasting/auth`, which is guarded by `auth:api` (the same JWT) and the `active` middleware, and whose rules are in `routes/channels.php`.

Real-time is off in sync mode: the API publishes nothing, and the React client uses a null gateway, so no connection is opened.

---

## The stock event path

Stock belongs to the Go service, so a stock change must start there.

1. Laravel calls the gRPC method (`SetStock`, `DeleteStock`, `ReserveStock`, `ReleaseStock`). In async mode the gateway adds the metadata `x-publish-events: 1`.
2. The Go interceptor reads the metadata and marks the request context. Without the mark nothing is published, so sync traffic and the seeder never produce events.
3. After the database transaction commits, the service hands an event to the publisher.
4. The publisher is a buffered goroutine with publisher confirms. It reconnects with back-off when the broker is lost.
5. The exchange `ec.events` (topic) routes `stock.changed` to the queue `ec.stock.changed`.
6. `php artisan inventory:consume` reads the queue, validates the message, drops duplicates by `event_id`, and broadcasts `StockChanged` on the public `stock` channel.

The event body is `event_id`, `type`, `product_id`, `stock`, `deleted`, `reason` (`set`, `deleted`, `reserved`, `released`), `version` and `occurred_at`. `version` is the Unix time in microseconds. It stays below the JavaScript safe integer limit, and the client ignores any event older than the last one applied for the same product.

---

## Channels and events

| Channel | Type | Events | Who receives |
| --- | --- | --- | --- |
| `products` | public | `.product.created`, `.product.updated`, `.product.deleted` | everyone |
| `stock` | public | `.stock.changed` | everyone |
| `orders.user.{id}` | private | `.order.created`, `.order.status_changed`, `.order.cancelled`, `.order.deleted` | the owner |
| `orders.admin` | private | the same order events | administrators |
| `cart.{id}` | private | `.cart.updated` | the owner |

The cart event carries the full cart, so every open tab and device of the owner converges to the same state. When a stock event drops below the quantity in a cart, the client shows a shortage warning.

---

## Messaging topology

| Object | Type | Purpose |
| --- | --- | --- |
| `ec.events` | topic exchange | Domain events, key `stock.changed` |
| `ec.stock.changed` | queue | Stock events for the consumer. Dead-letters to `ec.events.dlx` |
| `ec.events.dlx` | exchange | Receives rejected stock events |
| `ec.stock.changed.dlq` | queue | Stock events that failed three times or were malformed |
| `emails`, `broadcasts` | queues | Laravel jobs |
| `ec.jobs.failed` | exchange | Receives jobs that exhausted their tries |
| `emails.failed`, `broadcasts.failed` | queues | Failed jobs, kept for inspection and replay |

Go and Laravel declare the event objects with identical arguments, because RabbitMQ rejects a declaration that differs from the existing object. `php artisan messaging:topology` declares everything in advance and is safe to run repeatedly.

---

## Design decisions

| Decision | Reason |
| --- | --- |
| Reverb instead of a custom server | It is a ready server, as the requirement demands, and it runs on Windows without Docker |
| The inventory service publishes the event | It owns the quantity, so any change reaches the clients, including changes that do not come through Laravel |
| Opt-in publishing through gRPC metadata | The same service serves sync and async traffic and must stay silent for sync |
| Consumer inside Laravel | Reuses the Reverb connection and the broadcast classes, and keeps the Go service free of WebSocket knowledge |
| Manual acknowledgements, prefetch 20 | A crash returns the message to the queue, and one slow message cannot starve the consumer |
| Cache-based cart kept as is | Changing the cart storage was outside this requirement. Docker uses Redis so all containers share it |

---

## Known limits

- Stock event publishing in Go is best effort. If the broker is down longer than the buffer can absorb (1024 events by default), events are dropped. It is not a transactional outbox. A client that reconnects refetches the data, so the screen converges.
- The dashboard figures are computed in the browser from the latest 100 orders, because the API has no aggregate endpoint. The page says so when the total is larger.
