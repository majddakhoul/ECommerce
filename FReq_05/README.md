# FReq_05 - Requirement 5

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis 7">
<img src="https://img.shields.io/badge/k6-load%20tests-7D64FF?logo=k6&logoColor=white" alt="k6">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../README.md) · [Requirement 1](../FReq_01/README.md) · [Requirements 2 and 3](../FReq_02_03/README.md) · [Requirement 4](../FReq_04/README.md) · [Requirement 5](README.md) · [Documentation hub](../docs/README.md)

Part of the [ECommerce](../README.md) repository. Requirement 1 is [FReq_01](../FReq_01/README.md). Requirements 2 and 3 are [FReq_02_03](../FReq_02_03/README.md). Requirement 4, asynchronous queues and real-time updates, is [FReq_04](../FReq_04/README.md). This folder holds requirement 5, scalability and load distribution: the platform of requirement 4 running as three Laravel instances and three queue workers behind one nginx load balancer, with every piece of shared state in Redis, MySQL, RabbitMQ and the Go service.

[Documentation index](docs/README.md) · [Architecture](docs/architecture.md) · [Scaling](docs/scaling.md) · [Realtime](docs/realtime.md) · [Reliability](docs/reliability.md) · [Configuration](docs/configuration.md) · [Docker](docs/docker.md) · [Local set-up](docs/local-setup.md) · [Load testing](docs/load-testing.md) · [Testing](docs/testing.md) · [Security](docs/security.md) · [Compliance](docs/compliance.md) · [Troubleshooting](docs/troubleshooting.md) · [Verification status](docs/verification-status.md)

---

## Table of Contents

- [Requirement](#requirement)
- [Topology](#topology)
- [Shared State](#shared-state)
- [Load Distribution Test](#load-distribution-test)
- [Repository Layout](#repository-layout)
- [Components](#components)
- [Choose How to Run](#choose-how-to-run)
- [Run Step by Step](#run-step-by-step)
- [Demo Accounts](#demo-accounts)
- [Sync and Async Paths](#sync-and-async-paths)
- [Real-time](#real-time)
- [Reliability](#reliability)
- [Web Client](#web-client)
- [How the Requirements Connect](#how-the-requirements-connect)
- [Verification Status](#verification-status)
- [Tech Stack](#tech-stack)
- [License](#license)

---

## Requirement

Scalability and load distribution. The Laravel application runs as several instances, the instances are interchangeable, and a load balancer spreads the requests among them. nginx 1.30.2 is the load balancer.

| Need | Solution |
| --- | --- |
| Several Laravel instances | `app1`, `app2`, `app3`, one image started three times |
| Several queue workers | `worker1`, `worker2`, `worker3` competing on the same RabbitMQ queues |
| One entry point and a balancing policy | nginx with `least_conn` on port 8000 |
| Shared database, broker, inventory service, web client | One MySQL, one RabbitMQ, one Go service, one React container, used by every instance |
| Shared cache, so jobs do not run twice and the cart is the same on every instance | One Redis, an atomic lock on every job and every stock event, a lock per user on every cart change |
| Proof of distribution | k6: 500 requests over 2 minutes, counted per instance, plus a run in which one instance is stopped |
| Documentation and Postman | [scaling.md](docs/scaling.md), [load-testing.md](docs/load-testing.md), [Requirement-5-v5](ECBackend/postman/Requirement-5-v5.postman_collection.json) |

---

## Topology

```text
                         +--> app1 --+
browser / k6 --> nginx --+--> app2 --+--> MySQL, Redis, RabbitMQ, Go inventory
   (port 8000)           +--> app3 --+
                                          RabbitMQ --> worker1, worker2, worker3
Go inventory --stock.changed--> RabbitMQ --> consumer (one) --> Reverb (one) --> browsers
```

Every response carries `X-Served-By` with the instance that produced it and `X-Upstream` with the address that nginx chose. Details and the reasoning behind each choice are in [scaling.md](docs/scaling.md).

---

## Shared State

| State | Where it lives | Why every instance sees the same value |
| --- | --- | --- |
| Cart | Redis | Instances keep nothing in memory, so a cart written through `app1` is read through `app3`. A lock per user makes concurrent changes safe |
| Mail job de-duplication | Redis | The key and the lock are shared, so two workers cannot send the same mail |
| Stock event de-duplication | Redis | The same mechanism for the events consumed from RabbitMQ |
| Rate limiting and performance counters | Redis | One counter for the whole fleet |
| Orders, products, users | MySQL | One database |
| Stock | Go service and its MySQL | One owner of stock |
| Product images | One Docker volume mounted by every instance | Valid on a single Docker host only, see [scaling.md](docs/scaling.md#known-limits) |
| JWT | Signed with one secret shared by every instance | Any instance validates a token issued by another |

---

## Load Distribution Test

```powershell
docker compose --profile tools run --rm k6 run /scripts/scale.js
docker compose --profile tools run --rm k6 run /scripts/cart.js
.\loadtest\failover.ps1
```

The first command sends 500 requests at a constant rate over 120 seconds and prints how many each instance served. The third stops `app2` after 60 seconds and starts it again after 100 seconds while the load continues. See [loadtest/README.md](loadtest/README.md) and [load-testing.md](docs/load-testing.md).

--- | --- |
| Queue | RabbitMQ through the Laravel queue connection `rabbitmq` |
| WebSocket | Laravel Reverb, with Laravel Echo in the browser |
| With and without queues, same API | `/api/v4/sync/...` and `/api/v4/async/...` |
| Live products, stock, cart, orders | Public and private Reverb channels |
| Stock events from the inventory owner | The Go service publishes `stock.changed` to RabbitMQ |
| Web client | React, using every existing API, with an administrator dashboard |

---

## Repository Layout

```text
FReq_05/
├── ECBackend/            Laravel: v1 to v4, queues, events, consumer, locks, Postman
├── ECInventory/          Go: gRPC inventory service and stock.changed publisher
├── ECWeb/                React web client with the admin dashboard
├── proto/                The gRPC contract
├── loadtest/             k6: sync against async, distribution, cart, mail, failover
├── docker/nginx/         nginx.conf of the load balancer
├── docs/                 Cross-cutting documentation
├── LICENSE               MIT License
├── docker-compose.yml    The whole platform, three instances behind nginx
└── .env.example          Development values
```

---

## Components

| Component | Purpose | Guide |
|---|---|---|
| [`ECBackend`](ECBackend) | Laravel API with the sync and async paths, queued jobs, realtime events, the stock consumer | [README](ECBackend/README.md) |
| [`ECInventory`](ECInventory) | Go service that owns stock and publishes `stock.changed` | [README](ECInventory/README.md) |
| [`ECWeb`](ECWeb) | React client, clean architecture, shop and admin | [README](ECWeb/README.md) |
| [`proto`](proto) | The gRPC contract and the event contract | [README](proto/README.md) |
| [`loadtest`](loadtest) | k6: sync against async, distribution across instances, cart consistency, duplicate mails, failover | [README](loadtest/README.md) |
| [`docker-compose.yml`](docker-compose.yml) | The whole platform | [docker.md](docs/docker.md) |
| [`docs`](docs) | Architecture, scaling, realtime contract, reliability, configuration, compliance, testing | [index](docs/README.md) |

| Requirement | Routes | Postman collection |
| --- | --- | --- |
| 1. API and security | `/api/v1` | [FReq_02_03 collection](../FReq_02_03/ECBackend/postman/Requirement-1-v1.postman_collection.json) |
| 2. Communication between services | `/api/v2` | [FReq_02_03 collection](../FReq_02_03/ECBackend/postman/Requirement-2-v2.postman_collection.json) |
| 3. Performance and caching | `/api/v3` | [FReq_02_03 collection](../FReq_02_03/ECBackend/postman/Requirement-3-v3.postman_collection.json) |
| 4. Asynchronous queues and real-time | `/api/v4/sync`, `/api/v4/async` | [`Requirement-4-v4`](ECBackend/postman/Requirement-4-v4.postman_collection.json) |
| 5. Scalability and load distribution | `/api/v4/sync`, `/api/v4/async` through nginx | [`Requirement-5-v5`](ECBackend/postman/Requirement-5-v5.postman_collection.json) |

---

## Choose How to Run

| Option | Use it when | Status | Guide |
|---|---|---|---|
| Local without Docker | You have PHP, Go, Node.js, MySQL and RabbitMQ | Run on a development machine | [local-setup.md](docs/local-setup.md) |
| Docker Compose | You want everything started by one command | Run on a Windows machine with Docker Desktop, see [verification-status.md](docs/verification-status.md) | [docker.md](docs/docker.md) |

---

## Run Step by Step

Docker, in PowerShell from this folder:

```powershell
Copy-Item .env.example .env
```

Fill in `APP_KEY`, `JWT_SECRET`, `REVERB_APP_KEY` and `REVERB_APP_SECRET`, as explained in [docker.md](docs/docker.md#start). Then:

```powershell
docker compose up --build
```

| Address | Service |
|---|---|
| `http://localhost:5173` | React client |
| `http://localhost:8000` | Laravel API, through nginx and its three instances |
| `http://localhost:8025` | Mailpit, with `--profile mail` |
| `http://localhost:15672` | RabbitMQ management (guest, guest) |
| `ws://localhost:8080` | Reverb |

Stop with `docker compose down`, reset all data with `docker compose down -v`. For the run without Docker follow [local-setup.md](docs/local-setup.md).

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

---

## Sync and Async Paths

| Path | Mail | Realtime events | Stock events from Go |
|---|---|---|---|
| `/api/v4/sync/...` | Sent inside the request | None | None |
| `/api/v4/async/...` | Queued on RabbitMQ, sent by a worker | Published to Reverb | Published to RabbitMQ and consumed by Laravel |

Both paths use the same controllers, the same validation and the same response shapes, and every response carries `X-Processing-Mode`. `/api/v1` to `/api/v3` are unchanged. The design is in [architecture.md](docs/architecture.md#two-paths-one-code-base).

```text
React (Echo) <--WebSocket-- Reverb <-- Laravel API / stock consumer
Laravel API --> RabbitMQ --> queue worker
Go inventory --stock.changed--> RabbitMQ --> inventory:consume --> Reverb
```

---

## Real-time

| Channel | Type | Events |
|---|---|---|
| `products` | public | product created, updated, deleted |
| `stock` | public | stock changed |
| `orders.user.{id}` | private | order created, status changed, cancelled, deleted |
| `orders.admin` | private | the same order events, for administrators |
| `cart.{id}` | private | cart updated |

Private channels are authorized at `/api/broadcasting/auth` with the same JWT as the REST API. See [architecture.md](docs/architecture.md#channels-and-events) and [security.md](docs/security.md#channels).

---

## Reliability

Retries with back-off, a failed-job queue, a dead letter queue for stock events, de-duplication and ordering are described in [reliability.md](docs/reliability.md), together with six runbooks that prove them against a real broker, including a stopped and a killed worker.

---

## Web Client

A React 18 and TypeScript client with a Domain, Data, Presentation and Services structure and the visual identity of the Flutter client of requirement 1. It covers sign-in and registration, the catalog with search, categories and detail, the cart with live shortage warnings, orders with live status, the profile with the mode switch, and an administrator area with a dashboard, product, order, user and role management, and a Lab page that measures sync against async from the browser. Details: [ECWeb/README.md](ECWeb/README.md).

---

## How the Requirements Connect

- Requirement 2 made the Go service the owner of stock. Requirement 4 lets that owner announce every change, so any client sees the truth without polling.
- Requirement 3 cached the catalog. The v4 catalog skips that cache, because live quantities must never be served stale.
- `/api/v4/sync` behaves like the earlier versions and is the baseline that `/api/v4/async` is measured against.
- Requirement 5 changes no route and no response shape. It makes the code of requirement 4 safe to run several times at once.
- The Redis introduced for the cache of requirement 3 becomes the single source of truth for the cart, the locks and the job de-duplication.

---

## Verification Status

| Area | Status |
|---|---|
| Laravel tests | 187 passing, 12 of them new for requirement 5 |
| Go unit tests with the race detector (events, publisher, service, config) | Passing |
| React type check, 27 unit tests, production build | Passing |
| React pages rendered against a mocked API without console errors | Checked |
| RabbitMQ and the queue workers on a real broker | Executed in the Docker stack: three workers consumed the mail jobs, the topology was declared at start-up |
| Reverb and live updates between two browsers | Reverb container started. Delivery between two browsers was not confirmed in a recorded run |
| Docker stack, three instances and workers behind nginx 1.30.2 | Executed, all services healthy |
| k6: 500 requests over 2 minutes | Executed, 167 requests on each of the three instances |
| k6: one instance stopped for about 40 seconds | Executed, the other two served the load, 0.2 percent of requests without an instance header |
| k6: cart across instances | Executed, 400 reads, 0 mismatches |
| k6: duplicate mail audit with Mailpit | Executed with `MAIL_MAILER=smtp`: 51 orders, 102 messages, 102 distinct, 0 duplicated |

The complete list, including the exact limits of each check, is in [verification-status.md](docs/verification-status.md). The results of all five requirements, with the commands to reproduce them, are in the repository [documentation](../docs/README.md), page [results](../docs/results.md).

---

## Tech Stack

| Layer | Technology |
|---|---|
| API | Laravel 12, PHP 8.2+, `tymon/jwt-auth`, `spatie/laravel-permission`, `grpc/grpc`, `laravel/reverb`, `vladimir-yuldashev/laravel-queue-rabbitmq`, `php-amqplib` |
| Inventory service | Go 1.24, gRPC, protobuf, MySQL, `amqp091-go` |
| Web client | React 18, TypeScript, Vite, React Router, TanStack Query, Axios, Laravel Echo, `pusher-js`, Vitest |
| Messaging | RabbitMQ 3.13, Laravel Reverb |
| Data | MySQL 8, Redis 7 |
| Tooling | Docker Compose, PHPUnit, Go test, k6, Postman |

---

## License

This project is released under the [MIT License](../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
