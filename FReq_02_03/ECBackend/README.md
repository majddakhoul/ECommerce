# ECommerce Backend API

> Part of the [ECommerce](../../README.md) repository, requirements 2 and 3 ([FReq_02_03](../README.md)). Stock is owned by the Go [`ECInventory`](../ECInventory/README.md) service, which this API calls over gRPC. Requirement 1 is documented in [`FReq_01/ECBackend`](../../FReq_01/ECBackend/README.md), and its mobile client in [`ECFrontend`](../../FReq_01/ECFrontend/README.md). See also: [documentation index](../docs/README.md), [architecture](../docs/architecture.md), [testing](../docs/testing.md), [security](../docs/security.md), [measurements](../PERFORMANCE.md).

<p align="center">
<img src="https://laravel.com/img/logomark.min.svg" height="80" alt="Laravel">
</p>

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter&logoColor=white" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
<img src="https://img.shields.io/badge/Spatie-laravel--permission-6B46C1" alt="Spatie Laravel Permission">
<img src="https://img.shields.io/badge/PHPUnit-11-366488?logo=php&logoColor=white" alt="PHPUnit 11">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis 7">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../README.md)

---

## Table of Contents

- [Overview](#overview)
- [Functional Features](#functional-features)
- [Security Features Implemented](#security-features-implemented)
  - [JWT Authentication](#jwt-authentication)
  - [Login Attempt Limiting (Lock and Unlock)](#login-attempt-limiting-lock-and-unlock)
  - [Rate Limiting](#rate-limiting)
  - [Role-Based Access Control](#role-based-access-control)
  - [Account Protection Rules](#account-protection-rules)
  - [Unified Response and Error Format](#unified-response-and-error-format)
  - [Order and Cart Security](#order-and-cart-security)
  - [Performance Monitoring (Middleware)](#performance-monitoring-middleware)
  - [Security Headers](#security-headers)
- [Stock Management](#stock-management)
- [Performance and Caching (v3)](#performance-and-caching-v3)
- [Email Notifications](#email-notifications)
- [Project Structure](#project-structure)
- [Installation and Setup](#installation-and-setup)
  - [Running with Docker](#running-with-docker)
- [API Endpoints](#api-endpoints)
  - [Authentication](#authentication)
  - [Users](#users)
  - [Roles](#roles)
  - [User Roles](#user-roles)
  - [Products](#products)
  - [Cart](#cart)
  - [Orders](#orders)
- [Authorization Matrix](#authorization-matrix)
- [Response Examples](#response-examples)
- [HTTP Status Codes](#http-status-codes)
- [Testing](#testing)
- [Environment Configuration](#environment-configuration)
- [Postman Collection](#postman-collection)
- [References and Documentation](#references-and-documentation)
- [License](#license)

---

## Overview

The **ECommerce Backend API** is a server-side application built with Laravel 12. It provides a complete authentication layer based on JSON Web Tokens, user and role management, a product catalogue, a per-user shopping cart, and order management with role-based access control. The system has two built-in roles, `admin` and `user`, and additional roles can be created, renamed, deleted, and assigned to users at runtime.

The application follows a layered architecture in which every request passes through a form request, a controller, a service, and a repository before reaching the model. Validation, authorisation, business rules, and data access are therefore kept in separate, independently testable layers.

---

## Functional Features

- JWT-based registration, login, token refresh, and logout.
- Every login, registration, password change, and token refresh returns the user together with their roles and the access token.
- Profile viewing and update, and password change with automatic token reissue.
- User management for administrators: list with search, role and status filters and pagination, create, update, delete, and unlock.
- Role management for administrators: list, create, view, rename, delete, and list the users of a role.
- Product management: create, view, update, and delete products with an optional uploaded image, restricted to administrators for write operations.
- Product browsing for every authenticated user: pagination, text search, category filter, in-stock filter, and a category list.
- Shopping cart per user, stored in the application cache with a 7-day lifetime: view, add, change quantity, remove, clear, and checkout.
- Order management: place an order directly or from the cart, view orders, cancel, and change the status. Customers see only their own orders and only administrators change the status.
- Stock reservation on order placement and stock restoration on cancellation or deletion.
- Email notifications: the customer and every administrator are emailed when an order is placed, and the customer is emailed when an administrator changes the order status.
- Request performance monitoring through a middleware that logs duration, memory, and database query statistics for every API request.
- Assigning a role to a user and removing a role from a user.
- Role-aware update operations: administrators may modify every field, while regular users may modify only their own name and email.
- Limited number of failed login attempts with temporary account locking and administrator unlock.
- Account activation status, which blocks login and invalidates the use of existing tokens when disabled.
- Unified JSON structure for every success and error response.

---

## Security Features Implemented

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

**Unlock** An administrator can clear the lock and reset the counter immediately with `POST /api/v2/users/{id}/unlock`, without waiting for the lockout period to expire.

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

A middleware adds security headers to every API response, including `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`. Responses carry `Cache-Control: no-store`, except the v3 product list and detail, which use `private, no-cache` with an `ETag` so that clients can revalidate.

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

## Performance and Caching (v3)

The third requirement adds `/api/v3`. Every v1 and v2 route exists under v3 with the same contract. Only the product list and the product detail use an optimized read path, and a lab under `/api/v3/performance` lets an administrator measure the difference. The complete measurement procedure and the results table are in [`../PERFORMANCE.md`](../PERFORMANCE.md).

**Layers**

| Piece | File |
|---|---|
| Controller | `app/Http/Controllers/V3/ProductController.php` |
| Read service | `app/Services/CatalogService.php` |
| Read repository (raw rows, FULLTEXT or LIKE, chosen connection) | `app/Repositories/Eloquent/CatalogRepository.php` |
| Versioned cache | `app/Support/Performance/CatalogCache.php` |
| Replica selection and read-your-writes | `app/Support/Performance/ReplicaGuard.php` |
| Runtime switches | `app/Support/Performance/Flags.php` |
| Invalidation on product changes | `app/Observers/ProductObserver.php` |
| ETag and `304` | `app/Http/Middleware/ConditionalGet.php` |
| Diagnostic headers | `app/Http/Middleware/PerformanceHeaders.php` |
| Lab | `app/Services/Performance/PerformanceLab.php`, `app/Http/Controllers/V3/PerformanceController.php` |
| Large data and indexes | `php artisan perf:seed`, `php artisan perf:indexes add|drop|status` |

**What the cache stores.** The list page (rows and total) and the product row are cached in Redis under a key that contains a global version number, for example `catalog:v7:list:<hash>`. Saving or deleting a product increments the version, so every old key becomes unreachable at once and expires by TTL (`PERF_CACHE_TTL`, 120 seconds plus up to 10 percent jitter). Redis runs with `maxmemory 256mb` and `allkeys-lru`.

**Stock is never cached.** The cached rows carry no quantity. After the cache lookup the service asks the inventory service for the stock of the page in one `GetStocks` call, so a changed quantity is visible on the next request. For the same reason an order does not invalidate the catalog cache. The `in_stock` filter needs live data and always bypasses the cache.

**Search.** On MySQL a search of three or more characters uses `MATCH(name, description) AGAINST (? IN BOOLEAN MODE)` with a FULLTEXT index. Without the index, or for shorter terms, the query falls back to `LIKE`.

**Read replica.** With `PERF_REPLICA_ENABLED`, reads of the catalog go to the `mysql_replica` connection. After any product write a marker pins reads to the primary for `PERF_REPLICA_STICKY_SECONDS`, so a user always reads their own change even while the replica lags, and a cache rebuilt right after an invalidation is built from the primary.

**ETag.** The list and the detail answer with `ETag`, `Cache-Control: private, no-cache` and `Vary: Authorization`. The tag is computed from the final body, so a changed quantity changes it. A request with a matching `If-None-Match` receives `304` without a body.

**Response headers** on the v3 list and detail

| Header | Meaning |
|---|---|
| `X-Cache` | `HIT`, `MISS` or `BYPASS` |
| `X-Data-Source` | `cache`, the connection that served the read, or `primary` |
| `X-DB-Queries` | Number of SQL statements of the request, including authentication |
| `X-Response-Time-Ms` | Server time of the request |

**Runtime switches** (`cache`, `etag`, `replica`, `fulltext`, `invalidation`) are stored in Redis and changed with `PUT /api/v3/performance/flags`, so the same stack can be measured before and after without a restart. Their defaults come from `PERF_*_ENABLED` variables.

**Lab endpoints** (administrator only)

| Endpoint | Purpose |
|---|---|
| `GET /api/v3/performance/status` | Flags, row counts, existing indexes, cache version, replica lag |
| `PUT /api/v3/performance/flags` | Switch features on or off |
| `DELETE /api/v3/performance/flags` | Back to the defaults |
| `POST /api/v3/performance/cache/flush` | Increment the cache version |
| `GET /api/v3/performance/compare?scenario=list|search|category|detail|orders&runs=30` | Runs the v2 path and the v3 path in process and returns average, p50, p95, queries per run and cache hit ratio for both, plus the speed-up |
| `GET /api/v3/performance/explain?scenario=search_like|search_fulltext|category|orders_by_user` | MySQL `EXPLAIN` of the query |

The `orders` scenario compares lazy loading (one query per order for its items and one for its user, the N+1 problem) with eager loading (three queries in total).

**Tests.** `tests/Feature/Performance` holds the before and after tests: v3 returns exactly what v2 returns, a hit costs fewer queries than a miss, stock stays live on a hit, a product update invalidates, disabling invalidation exposes stale data, ETag and `304`, the flags, and the N+1 comparison.

---

## Email Notifications

Emails are sent synchronously through Laravel's mailer by `NotificationService`. A delivery failure is written to `storage/logs/laravel.log` as `Order email failed` and never breaks the order request.

| Trigger | Recipient | Template |
|---|---|---|
| Order placed | The customer | `emails/order_placed_user` |
| Order placed | Every user with the `admin` role | `emails/order_placed_admin` |
| Order status changed by an administrator | The order owner | `emails/order_status_updated` |

The status email shows the previous and the current status with a message specific to `confirmed`, `processing`, `delivered`, and `cancelled`, together with the order lines and the total.

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

Gmail requires an App Password, not the account password. With `MAIL_MAILER=log` (the default) emails are written to the log instead of being sent. After changing `.env` run `php artisan config:clear`.

---

## Project Structure

```
app/
├── DTOs/
├── Exceptions/
│   ├── ApiException.php
│   └── ApiExceptionRenderer.php
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Logging/
├── Mail/
├── Models/
├── Policies/
├── Providers/
├── Repositories/
│   ├── Cache/
│   ├── Contracts/
│   └── Eloquent/
├── Grpc/
├── Services/
│   └── Inventory/
├── Support/
└── Traits/ApiResponse.php
bootstrap/app.php
docker/
resources/views/emails/
config/api.php
database/
postman/
routes/api.php
tests/Feature/
```

| Path | Purpose |
|---|---|
| `DTOs/` | Data Transfer Objects |
| `ApiException.php` | Domain exception with static helpers |
| `ApiExceptionRenderer.php` | Centralised JSON error rendering |
| `Controllers/` | Auth, User, Role, Product, Cart, Order |
| `Middleware/` | EnsureUserIsActive, SecurityHeaders, PerformanceMiddleware |
| `Requests/` | Validation and authorisation |
| `Resources/` | JSON resources |
| `Logging/` | PerformanceLogFormatter |
| `Mail/` | OrderPlacedUserMail, OrderPlacedAdminMail, OrderStatusUpdatedMail |
| `Models/` | User, Product, Order, OrderItem |
| `Policies/` | User, Role, Product, and Order policies |
| `Providers/` | App, Auth, Repository service providers |
| `Cache/` | Cache-based cart repository |
| `Contracts/` | Repository interfaces |
| `Eloquent/` | Eloquent implementations |
| `Grpc/` | Generated protobuf messages and the inventory client |
| `Services/` | Auth, User, Role, Product, Cart, Order, Notification, and Catalog services, plus `Performance/` for cache, flags, and the lab |
| `Inventory/` | InventoryGatewayInterface and the gRPC implementation |
| `Support/` | AccessControl (roles), OrderStatus (statuses and transitions), `Performance/` (CatalogCache, Flags, ReplicaGuard) |
| `Traits/ApiResponse.php` | Standardised JSON responses |
| `bootstrap/app.php` | Routing, middleware, and exception setup |
| `docker/` | Container entrypoint |
| `resources/views/emails/` | Order email templates |
| `config/api.php` | Login attempt settings |
| `database/` | Migrations, factory, and seeders |
| `postman/` | Postman collections for requirements 1, 2 and 3 (v1, v2, v3) |
| `routes/api.php` | API routes registered under /api/v1, /api/v2 and /api/v3 |
| `tests/Feature/` | Authentication, lockout, user, role, product, cart, order, inventory, version, and performance tests |

The request flow is:

```text
Route → FormRequest (validation and authorisation) → Controller → Service → Repository (interface and Eloquent) → Model
```

---

## Installation and Setup

**Prerequisites**
* PHP 8.2 or higher
* Composer
* MySQL / MariaDB (or SQLite)

**Steps**

Edit `.env` after copying it and set the database connection before running the migration. The inventory service must be running first, see [local-setup.md](../docs/local-setup.md).

```bash
git clone <repository-url>
cd <project-folder>
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --port=8000
```

If a stale `composer.lock` is present, delete it and run `composer update` instead of `composer install`.

### Running with Docker

The Docker Compose file lives in [`FReq_02_03`](../docker-compose.yml), one level up, because the whole platform runs together. From the `FReq_02_03` folder:

```bash
cp .env.example .env
docker compose up --build
```

The `.env` file holds the application key, the JWT secret and the ports. The values in `.env.example` are for local development only. The first build compiles the PHP gRPC extension and takes several minutes. The entrypoint runs the migrations and the seeders once the databases and the inventory service are healthy. The containers, ports, environment file and everyday commands are in the [Docker guide](../docs/docker.md), which also states that this configuration has not been executed yet. To run without Docker, follow [local-setup.md](../docs/local-setup.md).

**Demo accounts** (password `password`)

| Email | Role |
|---|---|
| `admin@example.com` | `admin` |
| `user@example.com` | `user` |

---

## API Endpoints

The API is served under two base paths with the same endpoints and the same contract: `/api/v1` for the first requirement and `/api/v2` for the second requirement, where stock is managed by the inventory service. `routes/api.php` defines the routes once and registers them under both prefixes. All routes except register and login require the header `Authorization: Bearer {token}`.

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

---

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

## Testing

```bash
php artisan test
```

The test suite uses an in-memory SQLite database and replaces the gRPC gateway with an in-memory fake (`tests/Support/FakeInventoryGateway.php`), so it runs without the inventory service. It covers:

| File | Coverage |
|---|---|
| `AuthTest` | Registration, login, roles in the login response, token handling, disabled accounts, profile update, and password change |
| `LoginLockoutTest` | Failed attempt counting, lock, `423` response, counter reset, and unlock |
| `UserTest` | Listing and filtering, creation, role-aware updates, deletion, unlock, role assignment and removal, and last-administrator protection |
| `ProductTest` | Product CRUD, role restrictions, pagination, search, category and stock filters, image upload, replacement and cleanup, and not-found responses |
| `InventoryIntegrationTest` | Stock shown from the inventory service, product create, update, and delete forwarding, reservation on order, rejection messages, `503` when the service is down, release on failure and on cancellation |
| `Performance/CatalogBeforeAfterTest` | v2 against v3: same data, cache hit and miss, live stock on a hit, invalidation, stale data when invalidation is off, ETag and `304`, detail cache, `in_stock` bypass |
| `Performance/PerformanceLabTest` | Administrator-only lab, flags, cache flush, the N+1 comparison, the list comparison, explain |
| `ApiVersionTest` | The same products and orders under `/api/v1` and `/api/v2`, and the JSON `404` for an unknown version |
| `CartTest` | Cart operations, stock limits, privacy between users, checkout, and failed checkout without side effects |
| `OrderTest` | Placing orders, stock reservation and restoration, ownership rules, admin-only actions, status transitions, order history after product deletion, and the performance headers |
| `RoleTest` | Role CRUD, built-in role protection, deletion of assigned roles, authorisation, and not-found responses |

---

## Environment Configuration

Key `.env` variables:

```ini
APP_NAME="ECommerce API"
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auth_system
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=
JWT_TTL=60
JWT_REFRESH_TTL=20160

LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15

INVENTORY_GRPC_HOST=127.0.0.1
INVENTORY_GRPC_PORT=50051
INVENTORY_API_KEY=change-me
INVENTORY_GRPC_TIMEOUT=2

REDIS_HOST=127.0.0.1
PERFORMANCE_STORE=redis
PERF_CACHE_ENABLED=true
PERF_ETAG_ENABLED=true
PERF_REPLICA_ENABLED=false
PERF_FULLTEXT_ENABLED=true
PERF_CACHE_TTL=120
PERF_REPLICA_STICKY_SECONDS=5
DB_REPLICA_HOST=
API_RATE_LIMIT=120

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
QUEUE_CONNECTION=sync
```

Set `APP_DEBUG=false` outside local development. `JWT_SECRET` is generated with `php artisan jwt:secret`. Never commit the `.env` file.

---

## Postman Collection

Three ready-to-import collections live in [`postman/`](postman):

| File | Base path | Content |
|---|---|---|
| [`Requirement-1-v1.postman_collection.json`](postman/Requirement-1-v1.postman_collection.json) | `/api/v1` | All 41 requests of the first requirement |
| [`Requirement-2-v2.postman_collection.json`](postman/Requirement-2-v2.postman_collection.json) | `/api/v2` | The same 41 requests plus the folder `Requirement 2 - Inventory checks` |
| [`Requirement-3-v3.postman_collection.json`](postman/Requirement-3-v3.postman_collection.json) | `/api/v3` | The same requests plus the folders `Requirement 3 - Performance lab` and `Requirement 3 - Optimized catalog` |

Requests are numbered in the order they are typically used and grouped into Auth, Users, Roles, Products, Cart, and Orders. Log in once with either demo account and a collection script stores the JWT in the `token` variable for every other request.

The inventory folder runs seven requests in order: create a product with one unit, show it, order more than available (`409`), order the last unit (`201`), order again (`409`), cancel the order, and show the stock restored. Stopping the inventory service and placing an order returns `503`.

---

## References and Documentation

* **Laravel 12.x:** [https://laravel.com/docs/12.x](https://laravel.com/docs/12.x)
* **Tymon JWT Auth:** [https://jwt-auth.readthedocs.io/](https://jwt-auth.readthedocs.io/)
* **Spatie Laravel Permission:** [https://spatie.be/docs/laravel-permission](https://spatie.be/docs/laravel-permission)
* **PHPUnit:** [https://docs.phpunit.de/](https://docs.phpunit.de/)

---

## License

This project is released under the [MIT License](LICENSE). Copyright (c) 2026 Majd AlDakhoul.
