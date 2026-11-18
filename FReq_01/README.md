# ECommerce

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter&logoColor=white" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../README.md) · [Requirement 1](README.md) · [Requirements 2 and 3](../FReq_02_03/README.md) · [Requirement 4](../FReq_04/README.md)

Requirement 1 of the ECommerce project, built for the Web Applications course at Damascus University: a Laravel 12 REST API and a Flutter mobile client that consumes every endpoint of it.

[Backend documentation](ECBackend/README.md) · [Frontend documentation](ECFrontend/README.md) · [Postman collection](ECBackend/postman/ECommerce.postman_collection.json)

---

## Table of Contents

- [Repository Layout](#repository-layout)
- [How the Two Parts Connect](#how-the-two-parts-connect)
- [Features](#features)
- [Quick Start](#quick-start)
- [Demo Accounts](#demo-accounts)
- [Requirements 2 and 3](#requirements-2-and-3)
- [Requirement 4](#requirement-4)
- [Branches](#branches)
- [Tech Stack](#tech-stack)
- [License](#license)

---

## Repository Layout

```text
FReq_01/
├── ECBackend/     Laravel 12 REST API (JWT, roles, products, cart, orders, emails, Docker)
├── ECFrontend/    Flutter client (Cubit / BLoC, Dio)
├── LICENSE        MIT
└── README.md      this file
```

This folder is requirement 1 of the [ECommerce](../README.md) repository. Requirements 2 and 3 live in [FReq_02_03](../FReq_02_03/README.md).

| Folder | What it is | Documentation |
|---|---|---|
| `ECBackend` | Layered Laravel API with role-based access control, stock management, and email notifications | [ECBackend/README.md](ECBackend/README.md) |
| `ECFrontend` | Flutter app for customers and administrators | [ECFrontend/README.md](ECFrontend/README.md) |

---

## How the Two Parts Connect

```text
Flutter app (ECFrontend)
        │   HTTPS / JSON, Bearer JWT
        ▼
 Laravel API  /api/v1  (ECBackend)
        │
        ├── MySQL        users, roles, products, orders
        ├── Cache        shopping carts
        └── SMTP         order emails to customers and administrators
```

- The app calls the API under `/api/v1` and sends the JWT in the `Authorization` header.
- Every response uses the same envelope (`status`, `code`, `message`, `data`, plus `meta` and `links` when paginated), which the app parses in one place.
- The Admin tab of the app appears only for users with the `admin` role, mirroring the API's authorisation rules.
- During development the phone reaches the API through an ngrok tunnel. The tunnel address is set once in the app, or passed with `--dart-define=API_BASE_URL=...`.

---

## Features

**Customers**
- Register, log in, edit the profile, and change the password.
- Browse products with search, category, and stock filters.
- Keep a personal cart and check out, or buy a product directly.
- Follow and cancel their own orders, and receive an email when an order is placed or its status changes.

**Administrators**
- Manage users (including unlock), roles, and role assignments.
- Create, edit, and delete products with image upload.
- See all orders, update their status, and receive an email for every new order.

**Platform**
- JWT authentication with login lockout, rate limiting, and security headers.
- Stock is reserved when an order is placed and restored on cancellation.
- Request performance logging, a unified JSON error format, and a Postman collection with 41 requests.
- One-command Docker setup for the backend.

---

## Quick Start

**1. Start the backend**

```bash
cd ECBackend
cp .env.example .env
docker compose up --build
```

The API runs on `http://localhost:8000`, phpMyAdmin on `http://localhost:8080`. Without Docker, see the [manual setup](ECBackend/README.md#installation-and-setup).

**2. Expose it to a phone (optional)**

```bash
ngrok http 8000
```

**3. Start the app**

```bash
cd ECFrontend
flutter pub get
flutter run --dart-define=API_BASE_URL=https://your-tunnel.ngrok-free.dev
```

Skip the `--dart-define` flag on an emulator, Chrome, or Windows if you use the address described in the [frontend documentation](ECFrontend/README.md#api-address).

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

---

## Requirements 2 and 3

Requirements 2 and 3 extend this API in [FReq_02_03](../FReq_02_03/README.md) without changing its contract.

| Requirement | What it adds | Documentation |
|---|---|---|
| 2. Communication between services | Stock moves to an independent Go service reached over gRPC, with atomic reservation, `409` for insufficient stock and `503` when the service is down | [FReq_02_03/README.md](../FReq_02_03/README.md#requirement-2---communication-between-services), [ECInventory](../FReq_02_03/ECInventory/README.md) |
| 3. Performance and caching | Redis cache, `ETag`, FULLTEXT search, indexes, read replica and k6 measurements, on the `/api/v3` routes | [FReq_02_03/README.md](../FReq_02_03/README.md#requirement-3---performance-and-caching), [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md) |

The routes `/api/v2` and `/api/v3` keep the response format of `/api/v1`, so this client is designed to work against them. It has not been retested against them. The backend of the later requirements is documented in [FReq_02_03/ECBackend/README.md](../FReq_02_03/ECBackend/README.md).

---

## Requirement 4

Requirement 4 adds asynchronous queues and real-time updates in [FReq_04](../FReq_04/README.md), again without changing this contract: the routes `/api/v1` to `/api/v3` stay as they are.

| What it adds | Documentation |
|---|---|
| The paths `/api/v4/sync` and `/api/v4/async` over the same controllers, with RabbitMQ jobs and retries on the async path | [FReq_04/README.md](../FReq_04/README.md#sync-and-async-paths), [reliability.md](../FReq_04/docs/reliability.md) |
| Live products, stock, cart and orders through Laravel Reverb, with private channels authorized by the same JWT | [realtime.md](../FReq_04/docs/realtime.md), [architecture.md](../FReq_04/docs/architecture.md) |
| A React web client with an administrator dashboard that uses every endpoint of this API | [ECWeb/README.md](../FReq_04/ECWeb/README.md) |

The Flutter client of this requirement is unchanged and is not connected to the real-time channels.

---

## Branches

| Branch | Purpose |
|---|---|
| `master` | Stable branch |
| `checkout` | Working branch with all three requirements, merged into `master` through a pull request |

---

## Tech Stack

| Layer | Technology |
|---|---|
| API | Laravel 12, PHP 8.2+, `tymon/jwt-auth`, `spatie/laravel-permission` |
| Database | MySQL 8 |
| Mobile | Flutter 3.44, Dart 3.12, `flutter_bloc`, `dio`, `get`, `shared_preferences`, `image_picker` |
| Tooling | Docker Compose, PHPUnit, Postman, ngrok |

---

## License

This project is released under the [MIT License](LICENSE). Copyright (c) 2026 Majd AlDakhoul.
