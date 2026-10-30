# ECommerce Backend API

> Part of the [ECommerce](../README.md) project. The mobile client lives in [`ECFrontend`](../ECFrontend/README.md).

<p align="center">
<img src="https://laravel.com/img/logomark.min.svg" height="80" alt="Laravel">
</p>

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel" alt="Laravel 12.x">
<img src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php" alt="PHP 8.2+">
<img src="https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white" alt="MySQL 8.x">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT Auth">
<img src="https://img.shields.io/badge/Spatie-laravel--permission-6B46C1" alt="Spatie Laravel Permission">
<img src="https://img.shields.io/badge/PHPUnit-11-366488?logo=php&logoColor=white" alt="PHPUnit 11">
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

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

**Unlock** An administrator can clear the lock and reset the counter immediately with `POST /api/v1/users/{id}/unlock`, without waiting for the lockout period to expire.

A locked account is different from a disabled account. A lock is applied automatically and expires by itself, whereas `is_active = false` is set manually by an administrator, returns `403`, and remains until the administrator re-enables the account.

### Rate Limiting

Named rate limiters protect the endpoints against abuse.

| Limiter | Limit | Key |
|---|---|---|
| `api` | 120 requests/min | user ID or IP |
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

A middleware adds security headers to every API response, including `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`.

---

## Stock Management

Stock is reserved when an order is placed and restored when it is cancelled.

| Event | Effect on stock |
|---|---|
| Order placed (direct or from the cart) | Stock is reduced immediately and the order starts as `pending` |
| Order confirmed or moved to processing | No change |
| Order delivered | No change; delivered orders are final |
| Order cancelled by the customer or by an administrator | Stock is restored |
| Status changed to `cancelled` by an administrator | Stock is restored |
| Order deleted while still cancellable | Stock is restored |
| Order deleted after delivery or cancellation | No change, so stock is never restored twice |

The reduction is atomic (`stock >= quantity` is part of the update), so two simultaneous orders can never push the stock below zero. If one line of an order cannot be reserved, the lines already reserved are released and the request fails with `409`.

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

```text
app/
├── DTOs/                          # Data Transfer Objects
├── Exceptions/
│   ├── ApiException.php           # Domain exception with static helpers
│   └── ApiExceptionRenderer.php   # Centralised JSON error rendering
├── Http/
│   ├── Controllers/               # Auth, User, Role, Product, Cart, Order
│   ├── Middleware/                # EnsureUserIsActive, SecurityHeaders, PerformanceMiddleware
│   ├── Requests/                  # Validation and authorisation
│   └── Resources/                 # JSON resources
├── Logging/                       # PerformanceLogFormatter
├── Mail/                          # OrderPlacedUserMail, OrderPlacedAdminMail, OrderStatusUpdatedMail
├── Models/                        # User, Product, Order, OrderItem
├── Policies/                      # User, Role, Product, and Order policies
├── Providers/                     # App, Auth, Repository service providers
├── Repositories/
│   ├── Cache/                     # Cache-based cart repository
│   ├── Contracts/                 # Repository interfaces
│   └── Eloquent/                  # Eloquent implementations
├── Services/                      # Auth, User, Role, Product, Cart, Order, and Notification services
├── Support/                       # AccessControl (roles), OrderStatus (statuses and transitions)
└── Traits/ApiResponse.php         # Standardised JSON responses
bootstrap/app.php                  # Routing, middleware, and exception setup
docker/                            # Container entrypoint
resources/views/emails/            # Order email templates
config/api.php                     # Login attempt settings
database/                          # Migrations, factory, and seeders
postman/                           # Postman collection
routes/api.php                     # Versioned API routes
tests/Feature/                     # Authentication, lockout, user, role, product, cart, and order tests
```

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

```bash
git clone <repository-url>
cd <project-folder>
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
# configure your database connection in .env
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --port=8000
```

If a stale `composer.lock` is present, delete it and run `composer update` instead of `composer install`.

### Running with Docker

```bash
docker compose up --build
```

This starts three containers: the API on `http://localhost:8000`, MySQL 8 (host port `3307`, database `ecommerce`), and phpMyAdmin on `http://localhost:8080`. The entrypoint runs the migrations, the seeders, and `storage:link` before the server starts. Settings are read from your `.env`, and only the database connection is overridden to point at the `db` container.

```bash
docker compose up -d
docker compose logs -f app
docker compose stop
docker compose down -v
```

Uploaded images, logs, and the database live in named volumes and survive restarts. `down -v` removes them.

**Demo accounts** (password `password`)

| Email | Role |
|---|---|
| `admin@example.com` | `admin` |
| `user@example.com` | `user` |

---

## API Endpoints

The base path is `/api/v1`. All routes except register and login require the header `Authorization: Bearer {token}`.

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

The test suite uses an in-memory SQLite database and covers:

| File | Coverage |
|---|---|
| `AuthTest` | Registration, login, roles in the login response, token handling, disabled accounts, profile update, and password change |
| `LoginLockoutTest` | Failed attempt counting, lock, `423` response, counter reset, and unlock |
| `UserTest` | Listing and filtering, creation, role-aware updates, deletion, unlock, role assignment and removal, and last-administrator protection |
| `ProductTest` | Product CRUD, role restrictions, pagination, search, category and stock filters, image upload, replacement and cleanup, and not-found responses |
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

A ready-to-import collection covering all 41 requests lives at [`postman/ECommerce.postman_collection.json`](postman/ECommerce.postman_collection.json). Requests are numbered in the order they are typically used and grouped into Auth, Users, Roles, Products, Cart, and Orders. Log in once with either demo account and a collection script stores the JWT in the `token` variable for every other request.

---

## References and Documentation

* **Laravel 12.x:** [https://laravel.com/docs/12.x](https://laravel.com/docs/12.x)
* **Tymon JWT Auth:** [https://jwt-auth.readthedocs.io/](https://jwt-auth.readthedocs.io/)
* **Spatie Laravel Permission:** [https://spatie.be/docs/laravel-permission](https://spatie.be/docs/laravel-permission)
* **PHPUnit:** [https://docs.phpunit.de/](https://docs.phpunit.de/)

---

## License

This project is released under the [MIT License](LICENSE). Copyright (c) 2026 Majd AlDakhoul.
