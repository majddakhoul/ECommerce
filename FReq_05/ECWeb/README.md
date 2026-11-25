# ECWeb

> Part of the [ECommerce](../../README.md) repository, requirement 5 ([FReq_05](../README.md)). The React web client of the platform. It consumes the Laravel API in [`ECBackend`](../ECBackend/README.md) and receives live updates from Reverb. See also: [documentation index](../docs/README.md), [architecture](../docs/architecture.md), [local set-up](../docs/local-setup.md), [testing](../docs/testing.md).

<p align="center">
<img src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black" alt="React 18">
<img src="https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white" alt="TypeScript">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/Laravel%20Reverb-WebSocket-FF2D20?logo=laravel&logoColor=white" alt="Laravel Reverb">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../../FReq_04/README.md) · [Requirement 5](../README.md)

The visual identity comes from the Flutter client of requirement 1: the same palette, the same button, input, badge and card specifications, and the same letter-spaced brand header.

---

## Table of Contents

- [Run](#run)
- [Configuration](#configuration)
- [Pages](#pages)
- [Architecture](#architecture)
- [Sync and async mode](#sync-and-async-mode)
- [Real-time behaviour](#real-time-behaviour)
- [Design system](#design-system)
- [Scripts and tests](#scripts-and-tests)
- [Limits](#limits)

---

## Run

From `FReq_05/ECWeb`:

```powershell
npm install
```

```powershell
Copy-Item .env.example .env
```

Set `VITE_REVERB_APP_KEY` to the value of `REVERB_APP_KEY` in the backend, then:

```powershell
npm run dev
```

Open `http://localhost:5173`. The backend, RabbitMQ, Reverb, the worker and the consumer must be running, see [local-setup.md](../docs/local-setup.md), or use the Docker stack.

---

## Configuration

| Variable | Default | Meaning |
|---|---|---|
| `VITE_API_URL` | `http://localhost:8000` | API origin |
| `VITE_API_MODE` | `async` | Default mode. A user choice stored in the browser overrides it |
| `VITE_REVERB_APP_KEY` | empty | Reverb application key. Empty disables real-time |
| `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | `localhost`, `8080`, `http` | Reverb address as the browser sees it |

Vite reads these at build time. In Docker they are build arguments of the image.

---

## Pages

| Area | Route | Content |
|---|---|---|
| Auth | `/login`, `/register` | Sign in and create an account, with server validation messages |
| Shop | `/` | Search, "New In" hero row, category chips, product grid, pagination |
| Shop | `/products/:id` | Detail, quantity picker, add to cart, live stock badge |
| Shop | `/cart` | Quantities, removal, clearing, live shortage warnings, place order |
| Shop | `/orders` | My orders with status filter, cancellation, live status |
| Shop | `/profile` | Personal details, password change, mode switch, sign out |
| Admin | `/admin` | Dashboard: revenue, orders, average order, products, users, seven-day charts, status meters, top products, low stock, recent orders |
| Admin | `/admin/products` | Search, create, edit with image upload, delete, live stock |
| Admin | `/admin/orders` | Filter, status transitions allowed by the rules, delete |
| Admin | `/admin/users` | Search, filter by role, create, edit, delete, unlock, assign and remove roles |
| Admin | `/admin/roles` | Create, rename, delete. `admin` and `user` are protected |
| Admin | `/admin/lab` | Places real orders alternately on sync and async and compares response times |

The admin area is guarded by the `admin` role, and the whole shop by authentication.

---

## Architecture

```text
src/
├── Domain/
│   ├── Models/          Plain types: Product, Cart, Order, User, Realtime events
│   ├── Rules/           Pure functions: order status transitions, stock levels, access, money
│   ├── Repositories/    Interfaces the use cases depend on
│   └── UseCases/        React Query hooks per feature, dashboard and benchmark logic, realtime cache updates
├── Data/
│   ├── DTOs/            Wire types as the API sends them
│   ├── Mappers/         DTO to model conversion
│   ├── DataSources/     HTTP calls
│   ├── Repositories/    Implementations of the Domain interfaces
│   └── Realtime/        Echo gateway and a null gateway
├── Services/            Config, HTTP client, token storage, notifications, session events
└── Presentation/
    ├── App/             Composition root, providers, routes
    ├── Layouts/         Shop, admin, auth, route guards
    ├── Pages/           One component per screen
    ├── Components/      Buttons, fields, badges, modal, charts, product cards
    ├── Hooks/           Debounce, document title
    └── Styles/          Tokens and component styles
```

The dependency rule points inwards. Domain imports nothing from Data, Presentation or Services. Presentation reaches the API only through the use-case hooks, and those reach it only through repository interfaces supplied by `Presentation/App/container.ts`. That file is the single place where concrete classes are built, so a test can supply fakes.

---

## Sync and async mode

The base path is `${VITE_API_URL}/api/v4/${mode}`. The profile page switches the mode, stores it in the browser and reloads. In sync mode the client uses a null realtime gateway, opens no WebSocket and shows the pill "Live updates off".

---

## Real-time behaviour

| Event | Effect on the screen |
|---|---|
| Stock changed | Updates the quantity in lists, detail, cart and admin tables, and flashes the card. Events older than the last applied one for that product are ignored |
| Product created, updated, deleted | Updates or refetches the catalog lists |
| Cart updated | Replaces the cart, so every tab and device converges |
| Order events | Updates my orders or the admin orders and the dashboard |

When a stock event drops below a cart quantity, a warning appears and checkout is disabled until the cart is adjusted. On reconnect the client refetches, so events missed during an outage do not leave stale data.

---

## Design system

| Token | Value |
|---|---|
| primary, accent, accent light | `#554CA1`, `#8B72D8`, `#EDE4FB` |
| pink, pink soft | `#FCC9D2`, `#FBEEF0` |
| black, surface, border | `#000100`, `#F6F4FA`, `#E6E2EF` |
| text primary, secondary | `#14121F`, `#8A879A` |
| danger, success, warning, info | `#FF1D48`, `#2EAD6B`, `#F2A33A`, `#4C8DF0` |

Buttons are 54 px high with radius 14, inputs have radius 14 and an accent border of 1.5 px on focus, badges use radius 20 with 14 percent background, hero cards use radius 24. Tokens are CSS variables in `Presentation/Styles/tokens.css`.

---

## Scripts and tests

| Command | Purpose |
|---|---|
| `npm run dev` | Development server on port 5173 |
| `npm run build` | Type check and production build |
| `npm run typecheck` | Strict TypeScript check |
| `npm test` | 27 unit tests with Vitest |

The tests are described in [testing.md](../docs/testing.md#react).

---

## Limits

- The dashboard is computed in the browser from the latest 100 orders and says so when the total is larger, because the API has no aggregate endpoint.
- The token is kept in `localStorage`, see [security.md](../docs/security.md#tokens-in-the-browser).
- The live connection, the image upload and the full flows against the real API have not been run, see [verification-status.md](../docs/verification-status.md#react).

---

## License

This project is released under the [MIT License](../../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
