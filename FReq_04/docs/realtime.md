# Realtime contract

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md) · [Requirement 5](../../FReq_05/README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

This page is the contract of the real-time layer: which channels exist, who may join them, what every event carries and how the web client consumes them.

## Table of contents

- [Transport](#transport)
- [Channels](#channels)
- [Events and payloads](#events-and-payloads)
- [Channel authorization](#channel-authorization)
- [Event ordering and duplicates](#event-ordering-and-duplicates)
- [Client behaviour](#client-behaviour)
- [Connection states](#connection-states)
- [Allowed origins](#allowed-origins)
- [Inspecting events](#inspecting-events)
- [Related documents](#related-documents)

## Transport

Laravel Reverb is the WebSocket server. It speaks the Pusher protocol, so the browser uses `laravel-echo` with `pusher-js`. The application key in the browser (`VITE_REVERB_APP_KEY`) must equal `REVERB_APP_KEY` of the API.

| Part | Technology | Default address |
| --- | --- | --- |
| WebSocket server | `php artisan reverb:start` | `ws://localhost:8080` |
| Publisher | Laravel broadcasting, `BROADCAST_CONNECTION=reverb` | In process |
| Queue for broadcast jobs | RabbitMQ queue `broadcasts` | `127.0.0.1:5672` |
| Client | `laravel-echo` with a custom channel authorizer | Browser |

Events are published only on the `/api/v4/async` path. On `/api/v4/sync` no event is dispatched, and the web client does not open a WebSocket, so the two modes can be compared directly.

## Channels

| Channel | Type | Who may join | Carries |
| --- | --- | --- | --- |
| `products` | Public | Anyone | Product created, updated, deleted |
| `stock` | Public | Anyone | Stock changes coming from the inventory service |
| `private-orders.user.{userId}` | Private | The user with that id | The user's own order events |
| `private-orders.admin` | Private | Users with the role `admin` | Order events of every customer |
| `private-cart.{userId}` | Private | The user with that id | The user's cart after every change |

Laravel names private channels `orders.user.{id}` in code. On the wire the Pusher protocol prefixes them with `private-`, which `laravel-echo` does for you.

## Events and payloads

The listener name carries a leading dot because the events define their own `broadcastAs()` name, which `laravel-echo` expects as `.name`.

| Listener | Class | Channel | Queue | Delivery |
| --- | --- | --- | --- | --- |
| `.product.created` | `ProductChanged` | `products` | `broadcasts` | After commit |
| `.product.updated` | `ProductChanged` | `products` | `broadcasts` | After commit |
| `.product.deleted` | `ProductChanged` | `products` | `broadcasts` | After commit |
| `.stock.changed` | `StockChanged` | `stock` | Immediate | Sent by the consumer |
| `.cart.updated` | `CartUpdated` | `cart.{userId}` | `broadcasts` | After commit |
| `.order.created` | `OrderCreated` | `orders.user.{userId}` and `orders.admin` | `broadcasts` | After commit |
| `.order.status_changed` | `OrderStatusChanged` | `orders.user.{userId}` and `orders.admin` | `broadcasts` | After commit |
| `.order.cancelled` | `OrderCancelled` | `orders.user.{userId}` and `orders.admin` | `broadcasts` | After commit |
| `.order.deleted` | `OrderDeleted` | `orders.user.{userId}` and `orders.admin` | `broadcasts` | After commit |

### Product

```json
{
  "action": "updated",
  "product_id": 7,
  "product": {
    "id": 7,
    "name": "Wireless Mouse",
    "description": "Ergonomic wireless mouse",
    "price": "24.90",
    "cost": "12.00",
    "stock": 40,
    "in_stock": true,
    "category": "accessories",
    "image": null,
    "created_at": "2026-11-17T17:50:00.000000Z",
    "updated_at": "2026-11-17T18:00:00.000000Z"
  }
}
```

For `deleted`, `product` still describes the product as it was before deletion.

### Stock

```json
{
  "event_id": "0f0d3c52-8b6a-4b5e-9a56-3f2f0e6a8a11",
  "product_id": 7,
  "stock": 38,
  "deleted": false,
  "reason": "reserved",
  "version": 1731858000123456,
  "occurred_at": "2026-11-17T18:00:00Z"
}
```

| Field | Meaning |
| --- | --- |
| `event_id` | Unique id created by the inventory service, used for de-duplication |
| `product_id` | Product whose quantity changed |
| `stock` | Quantity after the change |
| `deleted` | `true` when the product was removed from the inventory |
| `reason` | `set` (stock written by an administrator), `reserved` (order placed), `released` (order cancelled or deleted) or `deleted` (product removed) |
| `version` | Microsecond timestamp of the change. A client ignores an event older than the last one it applied for the same product |
| `occurred_at` | Time of the change in the inventory service |

### Cart

```json
{
  "cart": {
    "items": [
      {
        "product_id": 7,
        "name": "Wireless Mouse",
        "price": "24.90",
        "quantity": 2,
        "subtotal": "49.80",
        "available_stock": 38
      }
    ],
    "total_quantity": 2,
    "total_price": "49.80"
  }
}
```

An empty cart is sent after a clear or a checkout.

### Order

All four order events share one shape:

```json
{
  "order_id": 31,
  "previous_status": "pending",
  "order": {
    "id": 31,
    "user_id": 2,
    "status": "confirmed",
    "total_price": "49.80",
    "items": [
      {
        "id": 55,
        "product_id": 7,
        "product_name": "Wireless Mouse",
        "quantity": 2,
        "price": "24.90",
        "subtotal": "49.80"
      }
    ],
    "delivered_at": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "updated_at": "2026-11-17T18:05:00.000000Z"
  }
}
```

The `order` object is produced by `OrderResource`, the same output as the REST endpoints, and may also contain the `user` object when the relation is loaded. `previous_status` carries the status before the change and is only meaningful for `.order.status_changed`. Field values in all examples are illustrative.

## Channel authorization

Public channels need no authorization. For a private channel the browser asks the API to sign the subscription:

1. Echo connects to Reverb and receives a `socket_id`.
2. Echo calls the custom authorizer with the channel name.
3. The authorizer sends `POST /api/broadcasting/auth` with `socket_id` and `channel_name`, using the same `Authorization: Bearer` header as every other request.
4. The route runs `auth:api` and `active`, then the callback of the channel in `routes/channels.php`.
5. On success the API returns the signature and the browser subscribes. On failure the API answers `401` or `403` and the subscription is refused.

| Channel | Rule |
| --- | --- |
| `orders.user.{userId}` | The authenticated user id equals `userId` |
| `orders.admin` | The user has the role `admin` |
| `cart.{userId}` | The authenticated user id equals `userId`, also for administrators |

The behaviour is covered by `BroadcastAuthTest`: owner access, foreign access, the admin channel, no token and a deactivated user.

## Event ordering and duplicates

- Broadcast jobs are processed by the queue worker, so two events of the same entity can arrive in a different order than they were created.
- Stock events carry `version`. `useRealtimeSync` keeps the last version per product and discards anything older.
- The consumer acknowledges a message only after the broadcast and ignores a repeated `event_id` for one hour, so a redelivered AMQP message does not produce a second event. Details are in [reliability.md](reliability.md).
- Product, cart and order events carry the full entity, so applying the same event twice gives the same screen.

## Client behaviour

| Concern | Implementation |
| --- | --- |
| Gateway | `EchoRealtimeGateway` implements the domain interface `RealtimeGateway`. `NullRealtimeGateway` is used in sync mode and when the key is missing |
| Subscriptions | Reference counted per channel, so two components listening to `products` open one subscription |
| Cache update | `useRealtimeSync` (in `Domain/UseCases/Realtime`) writes events into the React Query cache. Product events upsert or remove the entry and the lists, stock events change only the quantity in lists, details and the cart, cart events replace the cart, order events set the order and invalidate the order lists, which also refreshes the dashboard |
| Duplicates | Order events are de-duplicated by kind, order id, status and update time. Stock events are ordered by `version` |
| Reconnect | On the transition back to `connected`, the client invalidates the product, cart and order queries and refetches them, because events sent while the socket was down are not replayed |
| Notifications | Toasts through `Services/Notifications.ts`: a status change of the user's own order, a new product for customers, a cart line that a stock change made unavailable, and for administrators a new order or an order cancelled by a customer |
| Mode switch | Changing the mode stores it in `localStorage` and reloads the page, which rebuilds the gateway |

Realtime is enabled when the mode is `async` and `VITE_REVERB_APP_KEY` is not empty.

## Connection states

| Pusher state | Status in the interface | Meaning |
| --- | --- | --- |
| `initialized`, `connecting` | Connecting | First attempt in progress |
| `connected` | Live | Subscribed and receiving |
| `unavailable`, `disconnected` | Reconnecting | The socket dropped or was refused. Echo retries on its own |
| `failed` | Failed | The browser cannot use WebSockets |
| Not applicable | Disabled | Sync mode or no key |

A pill that stays on Reconnecting almost always means that the server refused the connection. Start `php artisan reverb:start --debug` and read the output.

## Allowed origins

Reverb checks the `Origin` header of the browser against `REVERB_ALLOWED_ORIGINS`. The setting takes host names only, without scheme or port:

```ini
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1
```

A full origin such as `http://localhost:5173` never matches, and every connection is refused with `Origin not allowed`. This is a different setting from `CORS_ALLOWED_ORIGINS`, which controls the REST API and does take full origins. After editing `.env` run `php artisan config:clear` and restart Reverb.

## Inspecting events

| Goal | How |
| --- | --- |
| See connections, subscriptions and messages | `php artisan reverb:start --debug` |
| See jobs waiting for the worker | RabbitMQ management UI, queue `broadcasts` |
| See stock events before the consumer | RabbitMQ management UI, queue `ec.stock.changed` |
| Watch events in the browser | Developer tools, Network, WS, select the connection, Messages |

## Related documents

- [Architecture](architecture.md) for where the real-time layer sits.
- [Reliability](reliability.md) for retries, the failed queues and the stock dead-letter queue.
- [Configuration](configuration.md) for every variable named above.
- [ECBackend README](../ECBackend/README.md#realtime-events) for the server side.
- [ECWeb README](../ECWeb/README.md) for the client side.
