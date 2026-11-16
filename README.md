# ECommerce

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter&logoColor=white" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis 7">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

A full-stack e-commerce application built in three requirements for the Web Applications course at Damascus University: a Laravel 12 REST API with a Flutter client (requirement 1), an independent Go inventory service reached over gRPC (requirement 2), and a measured performance and caching layer (requirement 3).

[Requirement 1](FReq_01/README.md) · [Requirements 2 and 3](FReq_02_03/README.md) · [Backend, requirement 1](FReq_01/ECBackend/README.md) · [Flutter client](FReq_01/ECFrontend/README.md) · [Backend, requirements 2 and 3](FReq_02_03/ECBackend/README.md) · [Inventory service](FReq_02_03/ECInventory/README.md) · [Documentation index](FReq_02_03/docs/README.md) · [Verification status](FReq_02_03/docs/verification-status.md)

---

## Table of Contents

- [Repository Layout](#repository-layout)
- [How the Parts Connect](#how-the-parts-connect)
- [Features](#features)
- [Quick Start](#quick-start)
- [Demo Accounts](#demo-accounts)
- [Documentation](#documentation)
- [Branches](#branches)
- [Tech Stack](#tech-stack)
- [Repository Rules](#repository-rules)
- [License](#license)

---

## Repository Layout

```text
ECommerce/
├── FReq_01/       Requirement 1: Laravel API (/api/v1) and the Flutter client
│   ├── ECBackend/
│   └── ECFrontend/
├── FReq_02_03/    Requirements 2 and 3: Laravel API (/api/v1, /api/v2, /api/v3), Go inventory service
│   ├── ECBackend/
│   ├── ECInventory/
│   ├── proto/
│   ├── loadtest/
│   ├── docker/
│   └── docs/
├── LICENSE        MIT
└── README.md      this file
```

| Folder | Requirement | Contents | Documentation |
|---|---|---|---|
| `FReq_01` | 1. API and security | Layered Laravel API with JWT, roles, cart, orders and emails, plus the Flutter client | [FReq_01/README.md](FReq_01/README.md) |
| `FReq_02_03` | 2. Communication between services, 3. Performance and caching | Laravel API with v1, v2 and v3, Go inventory service, gRPC contract, Redis cache, ETag, read replica, k6 measurements, Docker platform | [FReq_02_03/README.md](FReq_02_03/README.md) |

---

## How the Parts Connect

```text
Flutter app (FReq_01/ECFrontend)
        │   HTTPS / JSON, Bearer JWT
        ▼
 Laravel API (ECBackend)   /api/v1   stock inside Laravel            requirement 1
                           /api/v2   stock in the inventory service  requirement 2
                           /api/v3   cached and conditional reads    requirement 3
        │
        ├── MySQL        users, roles, products, orders
        ├── Redis        catalog cache and runtime flags
        ├── MySQL replica   catalog reads
        └── gRPC + x-api-key
                │
                ▼
        Go inventory service (ECInventory) ── MySQL: stock, reservations
```

- The three API versions run side by side in the code of `FReq_02_03`, so one request can be compared across versions on the same data.
- The Flutter client works against every version, because v2 and v3 keep the v1 response contract.
- Stock is never cached. Every catalog response reads it live from the inventory service.

---

## Features

**Requirement 1: API and security**
- JWT authentication with login lockout, rate limiting, role-based access control and security headers.
- Users, roles, products with image upload, carts, orders, and email notifications.
- Flutter client for customers and administrators.

**Requirement 2: Communication between services**
- Stock management moved to an independent Go service with its own database.
- One gRPC contract shared by both sides, protected by an API key.
- Atomic all-or-nothing reservation, idempotent reservation ids, release exactly once.
- `409` for insufficient stock, `503` with a 2 second deadline when the service is down, release when saving an order fails.

**Requirement 3: Performance and caching**
- Redis cache with versioned keys, TTL and invalidation, `ETag` with `304`, FULLTEXT search, composite indexes, and an optional read replica with a read-your-writes guard.
- Lab API and k6 scripts that measure p50, p95 and p99 before and after each step, and prove that updates appear immediately.

---

## Quick Start

**Requirement 1**

```bash
cd FReq_01/ECBackend
cp .env.example .env
docker compose up --build
```

**Requirements 2 and 3 with Docker**

```bash
cd FReq_02_03
cp .env.example .env
docker compose up --build
```

The API runs on `http://localhost:8000`. The Docker configuration of requirements 2 and 3 has not been executed yet, see the [Docker guide](FReq_02_03/docs/docker.md).

**Requirements 2 and 3 without Docker**

This is the set-up that was run and verified: PHP, Go and MySQL on Windows. Follow [local-setup.md](FReq_02_03/docs/local-setup.md).

**Flutter client**

```bash
cd FReq_01/ECFrontend
flutter pub get
flutter run --dart-define=API_BASE_URL=http://localhost:8000
```

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

---

## Documentation

| Question | Document |
|---|---|
| How is the whole system designed? | [architecture.md](FReq_02_03/docs/architecture.md) |
| How do I run it with Docker? | [docker.md](FReq_02_03/docs/docker.md) |
| How do I run it without Docker? | [local-setup.md](FReq_02_03/docs/local-setup.md) |
| How do I run the tests? | [testing.md](FReq_02_03/docs/testing.md) |
| How does the Go service prevent overselling? | [ECInventory/README.md](FReq_02_03/ECInventory/README.md) |
| What does the gRPC contract contain? | [proto/README.md](FReq_02_03/proto/README.md) |
| How does the v3 read path work? | [ECBackend/README.md](FReq_02_03/ECBackend/README.md#performance-and-caching-v3) |
| What were the measured results? | [PERFORMANCE.md](FReq_02_03/PERFORMANCE.md#results) |
| What was verified and what was not? | [verification-status.md](FReq_02_03/docs/verification-status.md) |
| Something failed | [troubleshooting.md](FReq_02_03/docs/troubleshooting.md) |

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
| API | Laravel 12, PHP 8.2+, `tymon/jwt-auth`, `spatie/laravel-permission`, `grpc/grpc` |
| Inventory service | Go 1.24, gRPC, protobuf, `database/sql` with MySQL |
| Data | MySQL 8 (primary, replica, inventory), Redis 7 |
| Mobile | Flutter 3.44, Dart 3.12, `flutter_bloc`, `dio` |
| Tooling | Docker Compose, PHPUnit, Go test, k6, Postman |

---

## Repository Rules

- The `master` branch is protected. Work happens on a branch and enters `master` through a pull request.
- Never commit a `.env` file. Only `.env.example` files with development values are committed.
- Generated code (`gen/`, `vendor/`) is not committed.
- Source code contains no comments. Intent is expressed through names and structure, and explanations live in the documentation.

---

## License

This project is released under the [MIT License](LICENSE). Copyright (c) 2026 Majd AlDakhoul.
