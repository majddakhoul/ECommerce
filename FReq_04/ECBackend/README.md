# ECBackend

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)). The Laravel API of the platform: authentication, users and roles, products, cart and orders under `/api/v1` to `/api/v4`, with queued work, real-time events and the stock consumer added by requirement 4. Earlier versions of this guide: [requirements 2 and 3](../../FReq_02_03/ECBackend/README.md), [requirement 1](../../FReq_01/ECBackend/README.md).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis 7">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md)

[Documentation index](../docs/README.md) · [Architecture](../docs/architecture.md) · [Realtime](../docs/realtime.md) · [Reliability](../docs/reliability.md) · [Configuration](../docs/configuration.md) · [Docker](../docs/docker.md) · [Local set-up](../docs/local-setup.md) · [Testing](../docs/testing.md) · [Security](../docs/security.md) · [Compliance](../docs/compliance.md) · [Troubleshooting](../docs/troubleshooting.md) · [Verification status](../docs/verification-status.md)

## Table of Contents

- [Overview](#overview)
- [Functional Features](#functional-features)
- [What Requirement 4 Adds](#what-requirement-4-adds)
- [Processing Mode](#processing-mode)
- [Queued Work](#queued-work)
- [Realtime Events](#realtime-events)
- [Channel Authorization](#channel-authorization)
- [Stock Consumer](#stock-consumer)
- [Security Features](#security-features)
- [Stock Management](#stock-management)
- [Email Notifications](#email-notifications)
- [Project Structure](#project-structure)
- [Installation and Setup](#installation-and-setup)
- [Running the Processes](#running-the-processes)
- [API Endpoints](#api-endpoints)
- [Authorization Matrix](#authorization-matrix)
- [Response Examples](#response-examples)
- [HTTP Status Codes](#http-status-codes)
- [Commands](#commands)
- [Environment Configuration](#environment-configuration)
- [Testing](#testing)
- [Postman Collection](#postman-collection)
- [References and Documentation](#references-and-documentation)
- [License](#license)

---

## Overview

The **ECommerce Backend API** is a server-side application built with Laravel 12. It provides JSON Web Token authentication, user and role management, a product catalogue, a per-user shopping cart, and order management with role-based access control. The system has two built-in roles, `admin` and `user`, and additional roles can be created, renamed, deleted and assigned at runtime.

The application follows a layered architecture in which every request passes through a form request, a controller, a service and a repository before reaching the model. Validation, authorisation, business rules and data access stay in separate, independently testable layers. Stock is owned by the Go service [`ECInventory`](../ECInventory/README.md), reached over gRPC.

The routes `/api/v1`, `/api/v2` and `/api/v3` of the earlier requirements are unchanged. This guide describes the final state of the application, so it covers `/api/v4`, which has two variants: `/api/v4/sync` and `/api/v4/async`.

---

## Functional Features

- JWT-based registration, login, token refresh and logout. Every login, registration, password change and refresh returns the user with their roles and the access token.
- Profile viewing and update, and password change with automatic token reissue.
- User management for administrators: list with search, role and status filters and pagination, create, update, delete and unlock.
- Role management for administrators: list, create, view, rename, delete and list the users of a role. Assigning and removing roles of a user.
- Product management with an optional uploaded image, restricted to administrators for write operations. Product browsing for every authenticated user with pagination, text search, category filter, in-stock filter and a category list.
- Shopping cart per user, stored in the application cache with a 7-day lifetime: view, add, change quantity, remove, clear and checkout.
- Order management: place an order directly or from the cart, view, cancel and change the status. Customers see only their own orders and only administrators change the status.
- Stock reservation on order placement and restoration on cancellation or deletion, through the inventory service.
- Email notifications for new orders and status changes, sent inline on the sync path and by a queue worker on the async path.
- Real-time events for products, stock, carts and orders on the async path, delivered through Laravel Reverb.
- Request performance monitoring through a middleware that logs duration, memory and database query statistics.
- Limited failed login attempts with temporary locking and administrator unlock. Account activation status that blocks login and invalidates existing tokens when disabled.
- Unified JSON structure for every success and error response.

---

## What Requirement 4 Adds

| Addition | Where it is described |
|---|---|
| Two paths over the same controllers: `/api/v4/sync` and `/api/v4/async` | [Processing Mode](#processing-mode) |
| Order mails as queued jobs on RabbitMQ with retries and failed-job queues | [Queued Work](#queued-work), [reliability.md](../docs/reliability.md) |
| Broadcast events for products, stock, carts and orders | [Realtime Events](#realtime-events), [realtime.md](../docs/realtime.md) |
| Private channel authorization with the same JWT | [Channel Authorization](#channel-authorization) |
| A consumer that turns `stock.changed` messages from the Go service into broadcasts | [Stock Consumer](#stock-consumer) |
| Commands, configuration and tests for all of the above | [Commands](#commands), [Environment Configuration](#environment-configuration), [Testing](#testing) |

---

## Processing Mode

Routes are registered once in `routes/api.php` and mounted under every prefix. The middleware alias `processing:sync` or `processing:async` stores the mode in the scoped service `App\Support\Processing\ProcessingMode`. Code that has a side effect asks that service, so no controller knows which path it serves.

| Side effect | `/api/v4/sync` | `/api/v4/async` |
|---|---|---|
| Order mails | Sent inside the request | `SendOrderMail` job on the `emails` queue |
| Realtime events | Skipped by `RealtimePublisher` | Dispatched on the `broadcasts` queue |
| Inventory metadata `x-publish-events: 1` | Not sent | Sent by `GrpcInventoryGateway`, so the Go service publishes `stock.changed` |
| Catalog reads | Fresh, no cache | Fresh, no cache |

Every response carries `X-Processing-Mode` with the mode that handled the request. The paths `/api/v1` to `/api/v3` carry no such header and stay synchronous. The bare `/api/v4` prefix does not exist and returns the JSON `404`.

The v4 catalog uses the non-optimized `ProductController`. The cache and `ETag` of requirement 3 are not used, because a cached quantity would hide the changes that requirement 4 makes visible.

---

## Queued Work

Jobs run on RabbitMQ through the `rabbitmq` queue connection (`vladimir-yuldashev/laravel-queue-rabbitmq`).

| Job or event | Queue | Tries | Back-off |
|---|---|---|---|
| `SendOrderMail` | `emails` | 5 | 10, 60, 300 and 900 seconds |
| Broadcast events (`ShouldBroadcast`) | `broadcasts` | 3 | 2 and 10 seconds |

- `SendOrderMail` carries the order id, the mail kind and the recipient. It rebuilds the message from the database when it runs and stores an idempotency key (`mail-sent:*`) in the cache after a successful delivery, so a redelivered job does not send the mail again.
- Events use `afterCommit`, so a rolled-back transaction publishes nothing.
- A job that exhausts its tries is rerouted to the exchange `ec.jobs.failed` and lands in `<queue>.failed`, for example `emails.failed`. Create these objects with `php artisan messaging:topology`.
- `StockChanged` is the only event sent with `ShouldBroadcastNow`. The consumer broadcasts it directly.

Run the worker with:

```powershell
php artisan queue:work rabbitmq --queue=emails,broadcasts --tries=3
```

The failure behaviour and the runbooks that prove it are in [reliability.md](../docs/reliability.md).

---

## Realtime Events

Services publish through `App\Services\Realtime\RealtimePublisher`, which does nothing on the sync path.

| Event class | Broadcast name | Channel | Published when |
|---|---|---|---|
| `ProductChanged` | `.product.created`, `.product.updated`, `.product.deleted` | `products` | A product is created, updated or deleted |
| `StockChanged` | `.stock.changed` | `stock` | The consumer receives a `stock.changed` message |
| `CartUpdated` | `.cart.updated` | `cart.{id}` | The cart changes: add, update, remove, clear, checkout |
| `OrderCreated` | `.order.created` | `orders.user.{id}`, `orders.admin` | An order is placed |
| `OrderStatusChanged` | `.order.status_changed` | `orders.user.{id}`, `orders.admin` | An administrator changes the status |
| `OrderCancelled` | `.order.cancelled` | `orders.user.{id}`, `orders.admin` | An order is cancelled |
| `OrderDeleted` | `.order.deleted` | `orders.user.{id}`, `orders.admin` | An administrator deletes an order |

Payloads, channel names on the wire and client examples are in [realtime.md](../docs/realtime.md).

---

## Channel Authorization

`routes/channels.php` holds the rules. `/api/broadcasting/auth` is registered with the prefix `api` and the middleware `auth:api` and `active`, so a disabled account cannot subscribe. The channel callbacks use the guard `api`, the same JWT as the REST API.

| Channel | Rule |
|---|---|
| `orders.user.{userId}` | The authenticated user id equals `userId` |
| `orders.admin` | The user has the role `admin` |
| `cart.{userId}` | The authenticated user id equals `userId`. An administrator cannot join another user's cart channel |

`products` and `stock` are public and need no authorization.

---

## Stock Consumer

`php artisan inventory:consume` reads the queue `ec.stock.changed` with `php-amqplib`.

| Step | Behaviour |
|---|---|
| Prefetch | 20 unacknowledged messages (`RABBITMQ_STOCK_PREFETCH`) |
| Validate | Malformed or invalid messages are rejected without requeue and go to `ec.stock.changed.dlq` |
| De-duplicate | An `event_id` seen in the last hour is acknowledged and ignored (`RABBITMQ_STOCK_DEDUPE_TTL`) |
| Broadcast | `StockChanged` is sent to the public `stock` channel, carrying only the validated fields |
| Failure | The message is republished with `x-attempts` incremented. After three attempts (`RABBITMQ_STOCK_MAX_ATTEMPTS`) it is dead-lettered |
| Acknowledge | Manual, after the broadcast |

The logic lives in `App\Services\Realtime\StockEventHandler` and is tested without a broker. The connection code is in `App\Support\Messaging`.

---

## Security Features

### JWT Authentication

Authentication uses the `tymon/jwt-auth` package through a dedicated `api` guard. Every route except registration and login requires a valid bearer token. Expired, malformed, and revoked tokens are rejected with `401`.

Changing the password logs the user out of the current token and issues a new one, and logging out invalidates the token immediately.

### Login Attempt Limiting (Lock and Unlock)

The number of consecutive failed login attempts is stored on the user record.

| Setting | Default | Environment variable |
|---|---|---|
| Maximum failed attempts | 5 | `LOGIN_MAX_ATTEMPTS` |
| Lockout duration | 15 minutes | `LOGIN_LOCKOUT_MINUTES` |

**Lock** After the maximum number of consecutive failed attempts, the account is locked for the configured duration. While it is locked, every login attempt returns `423 Locked`, even when the password is correct. A successful login before the limit is reached resets the counter.

**Unlock** An administrator can clear the lock and reset the counter immediately with `POST /api/v4/{mode}/users/{id}/unlock`, without waiting for the lockout period to expire.

A locked account is different from a disabled account. A lock is applied automatically and expires by itself, whereas `is_active = false` is set manually by an administrator, returns `403`, and remains until the administrator re-enables the account.

### Rate Limiting

Named rate limiters protect the endpoints against abuse.

| Limiter | Limit | Key |
|---|---|---|
| `api` | 120 requests/min, configurable with `API_RATE_LIMIT` (the load-test compose file raises it) | user ID or IP |
| `login` | 10 requests/min and 30 requests/min | email and IP, and IP alone |
| `register` | 10 requests/min | IP |

Exceeding a limit returns `429 Too Many Requests`.

### Role-Based Access Control

Roles are managed through the `spatie/laravel-permission` package. Access decisions are made by Laravel policies that check the user's role.

- `admin` can manage users and roles.
- `user` can view and update their own profile only.
- Additional roles can be created and assigned. They behave like regular users unless a policy grants them more.

Authorisation runs inside the form request, so an unauthorised request receives `403` before any validation is performed.

### Account Protection Rules

- An administrator cannot change their own roles, deactivate their own account, or delete their own account.
- The last administrator cannot be deleted, deactivated, or have the `admin` role removed.
- The built-in `admin` and `user` roles cannot be renamed or deleted.
- A role that is assigned to users cannot be deleted.
- Registration always assigns the `user` role. Any role or status sent by the client is ignored.
- A regular user who sends `is_active` or `password` when updating their own record has those fields ignored.

### Unified Response and Error Format

Every response, including framework-level errors such as unknown routes, missing models, validation failures, and throttling, uses the same JSON structure. A missing model returns a specific message such as `Role not found.` or `User not found.` instead of a generic routing error.

### Order and Cart Security

- A customer can view and cancel only their own orders. Another customer's order returns `403`.
- Only administrators can list all orders, change an order status, or delete an order.
- Status changes follow fixed transitions: `pending` to `confirmed`, `processing`, or `cancelled`; `confirmed` to `processing`, `delivered`, or `cancelled`; `processing` to `delivered` or `cancelled`. Delivered and cancelled orders are final.
- Order lines keep a snapshot of the product name and price, so the history stays intact when a product is later changed or deleted.
- The cart belongs to the authenticated user and is never exposed to other users.

### Performance Monitoring (Middleware)

`PerformanceMiddleware` wraps every API request. It generates a trace identifier, listens to the database queries, and writes a `REQUEST_START` and a `REQUEST_END` entry to the dedicated `performance` log channel (`storage/logs/performance-YYYY-MM-DD.log`, JSON formatted, kept for 14 days). The entries include the route, user, IP, request and response sizes, duration, memory used, peak memory, query count, total query time, and the slowest query.

Every response also carries the headers `X-Trace-Id`, `X-Response-Time-ms`, and `X-Memory-Used-kb`.

### Security Headers

A middleware adds security headers to every API response, including `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`. Responses carry `Cache-Control: no-store` on the v4 paths, because the v4 catalog is never cached.

---

## Stock Management

Stock is no longer stored in this application. The `products` table keeps the product data only (name, description, price, cost, category, image), and the quantity of every product lives in the separate [`ECInventory`](../ECInventory/README.md) service, which has its own MySQL database. The API talks to it over gRPC through `InventoryGatewayInterface`, implemented by `GrpcInventoryGateway` and bound in `InventoryServiceProvider`. The contract is [`proto/inventory.proto`](../proto/inventory.proto), documented in [`proto/README.md`](../proto/README.md).

| API operation | gRPC call |
|---|---|
| Product details, list, and cart | `GetStock`, `GetStocks` |
| `in_stock` filter | `ListAvailableProductIds` |
| Create or update a product with a `stock` value | `SetStock` |
| Delete a product | `DeleteStock` |
| Place an order (direct or from the cart) | `ReserveStock`, one call for all the lines |
| Cancel, delete, or change the status to `cancelled` | `ReleaseStock` |

An order is placed in this order: the request is validated, one `ReserveStock` call asks the inventory service to take the quantity of every line, and only when the answer is positive is the order created. The reservation id is stored on the order (`orders.reservation_id`) so a later cancellation releases exactly what was reserved, and only once.

| Event | Effect on stock |
|---|---|
| Order placed | The whole order is reserved in one call, or nothing is reserved |
| Order confirmed, processing, or delivered | No change |
| Order cancelled by the customer or by an administrator | The reservation is released |
| Order deleted while still cancellable | The reservation is released |
| Order deleted after delivery or cancellation | No change, so stock is never restored twice |

**Failure handling**

| Situation | Result |
|---|---|
| Not enough stock for one or more lines | `409` with the product, the requested quantity, and the available quantity; no order is created |
| Inventory service stopped or slow | Every call has a 2 second deadline, then the API answers `503` with a clear message instead of waiting |
| The order cannot be saved after the stock was reserved | The reservation is released automatically |
| The inventory service fails while cancelling | `503`, and the order keeps its status |
| Ten simultaneous orders for the last unit | One succeeds, nine receive `409` |

---

The stock calls above are the same on every path. On `/api/v4/async` the gateway adds the gRPC metadata `x-publish-events: 1`, which makes the inventory service publish a `stock.changed` event after each change.

---

## Email Notifications

On `/api/v4/sync` and on the earlier versions, emails are sent inline through Laravel's mailer by `NotificationService`. On `/api/v4/async` the same service dispatches a `SendOrderMail` job and the queue worker sends the message. A delivery failure never breaks the order request: inline failures are written to `storage/logs/laravel.log` as `Order email failed`, and queued failures are retried and then rerouted to `emails.failed`.

| Trigger | Recipient | Template |
|---|---|---|
| Order placed | The customer | `emails/order_placed_user` |
| Order placed | Every user with the `admin` role | `emails/order_placed_admin` |
| Order status changed by an administrator | The order owner | `emails/order_status_updated` |

The status email shows the previous and the current status with a message specific to `confirmed`, `processing`, `delivered` and `cancelled`, together with the order lines and the total.

**Gmail SMTP configuration**

```ini
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_FROM_ADDRESS=your_email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

Gmail requires an App Password, not the account password. With `MAIL_MAILER=log` emails are written to the log instead of being sent, which is the right choice for local development. After changing `.env` run `php artisan config:clear`.

---

## Project Structure

```text
ECBackend/
├── app/
│   ├── Console/Commands/       ConsumeStockEventsCommand, MessagingTopologyCommand, performance seeders
│   ├── Events/Realtime/        ProductChanged, StockChanged, CartUpdated, Order* events, shared trait
│   ├── Exceptions/             Domain exceptions rendered as unified JSON
│   ├── Http/
│   │   ├── Controllers/        Auth, User, Role, Product, Cart, Order, V3 lab and catalog
│   │   ├── Middleware/         SetProcessingMode, performance, security headers, active-user check
│   │   ├── Requests/           Validation and authorisation per action
│   │   └── Resources/          JSON shape of users, products, orders
│   ├── Jobs/                   SendOrderMail
│   ├── Models/                 User, Product, Order, OrderItem
│   ├── Policies/               Role-based access rules
│   ├── Providers/              Service wiring, inventory gateway binding
│   ├── Repositories/           Data access behind interfaces
│   ├── Services/
│   │   ├── Inventory/          gRPC gateway with the opt-in metadata
│   │   ├── Realtime/           RealtimePublisher, StockEventHandler
│   │   └── ...                 Auth, User, Role, Product, Cart, Order, Notification
│   └── Support/
│       ├── Messaging/          Stock topology and AMQP connection
│       └── Processing/         ProcessingMode
├── bootstrap/app.php           Middleware aliases, broadcasting routes, exception rendering
├── config/                     messaging, reverb, broadcasting, queue, cors, performance
├── database/                   Migrations and seeders
├── docker/                     Entrypoint with one container role per process
├── postman/                    Collections for requirements 1 to 4
├── routes/                     api.php (v1 to v4), channels.php
└── tests/                      Feature and unit tests, test doubles
```

---

## Installation and Setup

**Prerequisites**

- PHP 8.2 or higher with `pdo_mysql`, `mbstring`, `zip`, `grpc` and `sockets`. `sockets` is required by `php-amqplib` and is commented out in the default XAMPP `php.ini`.
- Composer 2.
- MySQL or MariaDB.
- A running RabbitMQ 3.13 or newer and, for the stock flow, the inventory service.

**Steps**

The full walk-through, including RabbitMQ, the inventory service and the web client, is [local-setup.md](../docs/local-setup.md). In short, from this folder:

```powershell
composer install
```

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

```powershell
php artisan jwt:secret
```

Edit `.env` (database, `CACHE_STORE=file`, Reverb key and secret), then:

```powershell
php artisan migrate --seed
```

```powershell
php artisan storage:link
```

```powershell
php artisan messaging:topology
```

`composer.lock` is committed and pins the RabbitMQ and Reverb packages. If you change `composer.json`, run `composer update` and commit the new lock file.

**Docker**

The compose file lives in [`FReq_04`](../docker-compose.yml). It starts this API as four containers from one image (`app`, `worker`, `consumer`, `reverb`) selected by `CONTAINER_ROLE`, see [docker.md](../docs/docker.md).

**Demo accounts** (password `password`)

| Email | Role |
|---|---|
| `admin@example.com` | `admin` |
| `user@example.com` | `user` |

---

## Running the Processes

Four processes make up the async path. Each runs in its own terminal from this folder, together with RabbitMQ and the inventory service:

| Process | Command |
|---|---|
| HTTP server | `php artisan serve --port=8000` |
| WebSocket server | `php artisan reverb:start` |
| Queue worker | `php artisan queue:work rabbitmq --queue=emails,broadcasts --tries=3` |
| Stock consumer | `php artisan inventory:consume` |

`composer dev` starts the four together. Without the worker, jobs wait in RabbitMQ and nothing is lost, see [reliability.md](../docs/reliability.md#runbook-1-a-stopped-worker-loses-nothing).

---

## API Endpoints

Every endpoint below exists under `/api/v4/sync` and `/api/v4/async` with the same method, body and response, and under `/api/v1` to `/api/v3` as described in the earlier guides. In the tables the base path is left out. All routes except register and login require the header `Authorization: Bearer {token}`.

Two endpoints are outside the version prefix:

| Method | Endpoint | Access | Description |
|---|---|---|---|
| POST | `/api/broadcasting/auth` | Any token | Authorizes a private WebSocket channel. Body: `socket_id`, `channel_name` |
| GET | `/up` | Public | Laravel health check |

### Authentication

| Method | Endpoint | Access | Description |
|---|---|---|---|
| POST | `/auth/register` | Public | Register a new user with the `user` role |
| POST | `/auth/login` | Public | Obtain a JWT together with the user and roles |
| GET | `/auth/me` | Any token | Current user with roles |
| PUT | `/auth/me` | Any token | Update own name and email |
| PUT | `/auth/password` | Any token | Change password and receive a new token |
| POST | `/auth/refresh` | Any token | Refresh the token |
| POST | `/auth/logout` | Any token | Invalidate the token |

### Users

| Method | Endpoint | Access | Description |
|---|---|---|---|
| GET | `/users` | Admin | List users with `search`, `role`, `is_active`, and `per_page` filters |
| POST | `/users` | Admin | Create a user with an optional role |
| GET | `/users/{id}` | Self or admin | Show a user |
| PUT | `/users/{id}` | Self or admin | Update a user |
| DELETE | `/users/{id}` | Admin | Delete a user |
| POST | `/users/{id}/unlock` | Admin | Clear a login lock |

### Roles

| Method | Endpoint | Access | Description |
|---|---|---|---|
| GET | `/roles` | Admin | List roles with user counts |
| POST | `/roles` | Admin | Create a role |
| GET | `/roles/{id}` | Admin | Show a role |
| PUT | `/roles/{id}` | Admin | Rename a role |
| DELETE | `/roles/{id}` | Admin | Delete a role |
| GET | `/roles/{id}/users` | Admin | List the users of a role |

Role names must be lowercase, between 2 and 50 characters, contain only letters, digits, hyphens, and underscores, and be unique.

### User Roles

| Method | Endpoint | Access | Description |
|---|---|---|---|
| POST | `/users/{id}/roles` | Admin | Assign a role using `role_id` |
| DELETE | `/users/{id}/roles/{roleId}` | Admin | Remove a role from the user |

### Products

| Method | Endpoint | Access | Description |
|---|---|---|---|
| GET | `/products` | Any token | List products with `search`, `category`, `in_stock`, and `per_page` filters |
| GET | `/products/categories` | Any token | List the distinct categories |
| GET | `/products/{id}` | Any token | Show a product |
| POST | `/products` | Admin | Create a product, optionally with an `image` file |
| PUT | `/products/{id}` | Admin | Update a product |
| DELETE | `/products/{id}` | Admin | Delete a product and its image |

Product fields are `name`, `description`, `price`, `cost`, `stock`, `category`, and `image`. Categories are stored in lowercase. Images must be JPG, PNG, or WebP and at most 2 MB, and are stored on the `public` disk (run `php artisan storage:link` once so that image URLs resolve).

To upload an image during an update, send a `POST` request with the form field `_method=PUT`, because PHP does not parse multipart bodies on `PUT`. Updates without an image can use a plain JSON `PUT`.

### Cart

| Method | Endpoint | Access | Description |
|---|---|---|---|
| GET | `/cart` | Any token | View the cart with totals |
| POST | `/cart/items` | Any token | Add a product with `product_id` and `quantity` |
| PUT | `/cart/items/{productId}` | Any token | Set the quantity of a product in the cart |
| DELETE | `/cart/items/{productId}` | Any token | Remove a product from the cart |
| DELETE | `/cart` | Any token | Empty the cart |
| POST | `/cart/checkout` | Any token | Convert the cart into an order |

The cart is stored through the application cache (`CACHE_STORE`) under the key `cart:user:{id}` for seven days, so no Redis server is required. Adding or updating an item is rejected with `409` when the quantity exceeds the available stock.

### Orders

| Method | Endpoint | Access | Description |
|---|---|---|---|
| GET | `/orders` | Admin | List all orders with `status`, `user_id`, and `per_page` filters |
| GET | `/orders/my` | Any token | List the current user's orders |
| POST | `/orders` | Any token | Place an order from an `items` array of `product_id` and `quantity` |
| GET | `/orders/{id}` | Owner or admin | Show an order with its items |
| PUT | `/orders/{id}/status` | Admin | Change the status |
| POST | `/orders/{id}/cancel` | Owner or admin | Cancel an order and restore the stock |
| DELETE | `/orders/{id}` | Admin | Delete an order |

Order statuses are `pending`, `confirmed`, `processing`, `delivered`, and `cancelled`. A new order starts as `pending`. Placing an order checks the stock of every line first, then reserves it, and if any line cannot be reserved the lines already reserved are released and nothing is created.

---

## Authorization Matrix

| Operation | Admin | User |
|---|---|---|
| View and update own profile | Yes | Yes |
| Change own password | Yes | Yes |
| List users | Yes | No |
| View another user | Yes | No |
| Create a user | Yes | No |
| Update another user | Yes, all fields | No |
| Update own record | Name and email | Name and email only |
| Delete a user | Yes, except self | No |
| Unlock a user | Yes | No |
| Manage roles | Yes | No |
| List and view products, list categories | Yes | Yes |
| Create, update, or delete products | Yes | No |
| Use own cart (view, add, update, remove, clear, checkout) | Yes | Yes |
| Place an order | Yes | Yes |
| View and cancel own orders | Yes | Yes |
| View or cancel another user's order | Yes | No |
| List all orders, change status, delete an order | Yes | No |
| Assign or remove a user's role | Yes, except own roles | No |

---

## Response Examples

**Login**

```json
{
  "status": true,
  "code": 200,
  "message": "Logged in successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Super Admin",
      "email": "admin@example.com",
      "is_active": true,
      "is_locked": false,
      "roles": ["admin"],
      "last_login_at": "2026-10-27T15:00:00.000000Z"
    },
    "access_token": "<jwt>",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

**Resource not found**

```json
{
  "status": false,
  "code": 404,
  "message": "Role not found.",
  "data": null
}
```

**Validation error**

```json
{
  "status": false,
  "code": 422,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Paginated responses additionally contain `meta` (current page, per page, total, last page) and `links` (first, last, previous, next). Delete operations return `204 No Content` with no body.


**Response header on every v4 request**

```text
X-Processing-Mode: async
```

---

## HTTP Status Codes

| Code | Meaning |
|---|---|
| 200 | Success |
| 201 | Resource created |
| 204 | Deleted, no content |
| 401 | Missing, invalid, or expired token, or wrong credentials |
| 403 | Not allowed, or the account is disabled |
| 404 | Resource or endpoint not found |
| 405 | Method not allowed |
| 409 | Conflict with a business rule |
| 422 | Validation failed |
| 423 | Account locked after too many failed logins |
| 429 | Too many requests |
| 500 | Server error |

---

## Commands

| Command | Purpose |
|---|---|
| `php artisan messaging:topology` | Declares every exchange and queue, safe to repeat |
| `php artisan inventory:consume` | Runs the stock consumer. `--max-messages=N` stops after N messages |
| `php artisan queue:work rabbitmq --queue=emails,broadcasts` | Processes jobs |
| `php artisan reverb:start` | Starts the WebSocket server. `--debug` prints every connection and message |
| `php artisan migrate --seed` | Creates the schema and the demo data |
| `php artisan migrate:fresh --seed` | Resets the database. Product ids change, so reseed the inventory service |
| `php artisan config:clear` | Clears cached configuration after editing `.env` |
| `composer dev` | Starts the server, the worker, Reverb and the consumer together |

---

## Environment Configuration

The complete list of variables of every component, with defaults, is in [configuration.md](../docs/configuration.md). The ones that matter for requirement 4:

```ini
QUEUE_CONNECTION=rabbitmq
BROADCAST_CONNECTION=reverb
CACHE_STORE=file

RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

REVERB_APP_ID=ecommerce
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173

INVENTORY_GRPC_HOST=127.0.0.1
INVENTORY_GRPC_PORT=50051
INVENTORY_API_KEY=change-me
INVENTORY_GRPC_TIMEOUT=2
```

`REVERB_ALLOWED_ORIGINS` takes host names only. A full origin such as `http://localhost:5173` makes Reverb refuse every connection with `Origin not allowed`. `REVERB_APP_KEY` must equal `VITE_REVERB_APP_KEY` of the web client. The cart, the stock event de-duplication and the mail idempotency keys live in the cache, so every process must share the same cache store: `file` works on one machine, Redis is needed across containers. Set `APP_DEBUG=false` outside local development, generate `APP_KEY` and `JWT_SECRET` with `php artisan key:generate` and `php artisan jwt:secret`, and never commit the `.env` file.

---

## Testing

```powershell
php artisan test
```

The suite uses an in-memory SQLite database. It replaces the gRPC gateway with an in-memory fake (`tests/Support/FakeInventoryGateway.php`) and the Pusher client with `tests/Support/PusherLikeBroadcaster.php`, so it runs without the inventory service, RabbitMQ or Reverb. It has 175 tests.

| File | Coverage |
|---|---|
| `AuthTest` | Registration, login, roles in the login response, token handling, disabled accounts, profile update, password change |
| `LoginLockoutTest` | Failed attempt counting, lock, `423`, counter reset, unlock |
| `UserTest` | Listing and filtering, creation, role-aware updates, deletion, unlock, role assignment and removal, last-administrator protection |
| `ProductTest` | Product CRUD, role restrictions, pagination, search, category and stock filters, image upload, replacement and cleanup |
| `InventoryIntegrationTest` | Stock from the inventory service, forwarding of create, update and delete, reservation, rejection messages, `503`, release on failure and cancellation |
| `CartTest`, `OrderTest`, `RoleTest` | Cart operations, order rules and transitions, role CRUD |
| `ApiVersionTest` | The same data under every version and the JSON `404` for an unknown version |
| `Performance/*` | The v3 cache, `ETag`, invalidation and the lab API |
| `V4/ProcessingModeTest` | Mode follows the path, header, both modes expose authentication, earlier versions carry no header, no bare `/api/v4` |
| `V4/OrderProcessingTest` | Async queues the mail job and publishes events, sync sends inline and publishes nothing, idempotent delivery, cancellation restocks and publishes, channels of order events |
| `V4/RealtimeEventsTest` | Product, cart and checkout events, silence on the sync path, the gateway asks the inventory service to publish only in async mode |
| `V4/StockEventHandlerTest` | A valid event is broadcast once, a new event id is broadcast again, malformed events are rejected, public `stock` channel |
| `V4/BroadcastAuthTest` | Owner access, foreign access, admin channel, admin versus customer cart, no token, deactivated user |

Run the suite again after `composer install` to confirm nothing depends on the test double for the Pusher client.

---

## Postman Collection

Four ready-to-import collections live in [`postman/`](postman):

| File | Base path | Content |
|---|---|---|
| [`Requirement-1-v1.postman_collection.json`](postman/Requirement-1-v1.postman_collection.json) | `/api/v1` | All requests of the first requirement |
| [`Requirement-2-v2.postman_collection.json`](postman/Requirement-2-v2.postman_collection.json) | `/api/v2` | The same requests plus the inventory checks |
| [`Requirement-3-v3.postman_collection.json`](postman/Requirement-3-v3.postman_collection.json) | `/api/v3` | The same requests plus the performance lab and the optimized catalog |
| [`Requirement-4-v4.postman_collection.json`](postman/Requirement-4-v4.postman_collection.json) | `/api/v4/{mode}` | Auth, users, roles, products, cart, orders, and the folder `Requirement 4 - Async and real-time` |

Set the collection variable `mode` to `sync` or `async`. Log in once with a demo account and a collection script stores the JWT in `token`. The folder `Requirement 4 - Async and real-time` checks the mode header, places an order for a timing comparison, and tests broadcast authorization for the own, the admin and a foreign channel.

---

## References and Documentation

- **Laravel 12.x:** [https://laravel.com/docs/12.x](https://laravel.com/docs/12.x)
- **Laravel Reverb:** [https://laravel.com/docs/12.x/reverb](https://laravel.com/docs/12.x/reverb)
- **Laravel Broadcasting:** [https://laravel.com/docs/12.x/broadcasting](https://laravel.com/docs/12.x/broadcasting)
- **Laravel Queues:** [https://laravel.com/docs/12.x/queues](https://laravel.com/docs/12.x/queues)
- **Laravel Queue RabbitMQ:** [https://github.com/vyuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq)
- **php-amqplib:** [https://github.com/php-amqplib/php-amqplib](https://github.com/php-amqplib/php-amqplib)
- **RabbitMQ:** [https://www.rabbitmq.com/docs](https://www.rabbitmq.com/docs)
- **Tymon JWT Auth:** [https://jwt-auth.readthedocs.io/](https://jwt-auth.readthedocs.io/)
- **Spatie Laravel Permission:** [https://spatie.be/docs/laravel-permission](https://spatie.be/docs/laravel-permission)
- **PHPUnit:** [https://docs.phpunit.de/](https://docs.phpunit.de/)

---

## License

This project is released under the [MIT License](../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
