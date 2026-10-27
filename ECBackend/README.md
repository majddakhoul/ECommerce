# Authentication and Role Management API

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
  - [Security Headers](#security-headers)
- [Project Structure](#project-structure)
- [Installation and Setup](#installation-and-setup)
- [API Endpoints](#api-endpoints)
  - [Authentication](#authentication)
  - [Users](#users)
  - [Roles](#roles)
  - [User Roles](#user-roles)
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

The **Authentication and Role Management API** is a server-side application built with Laravel 12. It provides a complete authentication layer based on JSON Web Tokens, a user management module, and a role management module. The system has two built-in roles, `admin` and `user`, and additional roles can be created, renamed, deleted, and assigned to users at runtime.

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

### Security Headers

A middleware adds security headers to every API response, including `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`.

---

## Project Structure

```text
app/
├── DTOs/                          # Data Transfer Objects
├── Exceptions/
│   ├── ApiException.php           # Domain exception with static helpers
│   └── ApiExceptionRenderer.php   # Centralised JSON error rendering
├── Http/
│   ├── Controllers/               # Auth, User, Role, Product
│   ├── Middleware/                # EnsureUserIsActive, SecurityHeaders
│   ├── Requests/                  # Validation and authorisation
│   └── Resources/                 # JSON resources
├── Models/                        # User, Product
├── Policies/                      # UserPolicy, RolePolicy, ProductPolicy
├── Providers/                     # App, Auth, Repository service providers
├── Repositories/
│   ├── Contracts/                 # Repository interfaces
│   └── Eloquent/                  # Eloquent implementations
├── Services/                      # AuthService, UserService, RoleService, ProductService
├── Support/AccessControl.php      # Role names
└── Traits/ApiResponse.php         # Standardised JSON responses
bootstrap/app.php                  # Routing, middleware, and exception setup
config/api.php                     # Login attempt settings
database/                          # Migrations, factory, and seeders
postman/                           # Postman collection
routes/api.php                     # Versioned API routes
tests/Feature/                     # Authentication, lockout, user, role, and product tests
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
```

Set `APP_DEBUG=false` outside local development. `JWT_SECRET` is generated with `php artisan jwt:secret`. Never commit the `.env` file.

---

## Postman Collection

A ready-to-import collection covering all 28 requests lives at [`postman/Auth-Roles.postman_collection.json`](postman/Auth-Roles.postman_collection.json). Requests are numbered in the order they are typically used and grouped into Auth, Users, Roles, and Products. Log in once with either demo account and a collection script stores the JWT in the `token` variable for every other request.

---

## References and Documentation

* **Laravel 12.x:** [https://laravel.com/docs/12.x](https://laravel.com/docs/12.x)
* **Tymon JWT Auth:** [https://jwt-auth.readthedocs.io/](https://jwt-auth.readthedocs.io/)
* **Spatie Laravel Permission:** [https://spatie.be/docs/laravel-permission](https://spatie.be/docs/laravel-permission)
* **PHPUnit:** [https://docs.phpunit.de/](https://docs.phpunit.de/)

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).