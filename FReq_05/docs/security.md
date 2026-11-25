# Security

> Part of the [ECommerce](../../README.md) repository, requirement 5 ([FReq_05](../README.md)).

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

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../../FReq_04/README.md) · [Requirement 5](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Scaling](scaling.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Load testing](load-testing.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

## Secrets

- `.env` files are not committed. The repository holds only `.env.example` files with empty or development values.
- Rotate any secret that was ever shared or committed: the mail app password, `APP_KEY`, `JWT_SECRET`. Rotation was recommended and has to be done by the owner of the accounts.
- `REVERB_APP_SECRET` signs the publish calls from Laravel to Reverb and the private channel authorizations. `REVERB_APP_KEY` is public by design, it ends up in the browser bundle.
- The default RabbitMQ account `guest` only works from the same host in a plain installation. In Docker it is reachable from other containers, so change `RABBITMQ_USER` and `RABBITMQ_PASSWORD` for anything beyond local development.

## Several instances

- Every instance and worker reads the same `APP_KEY` and `JWT_SECRET` from one environment anchor. A token issued by one instance is valid on all of them, and a different secret on one instance would make it reject valid tokens.
- Redis holds carts, locks and rate-limit counters, and it is published on port 6379 in Compose for convenience. It has no password in the development file. Remove the `ports` entry or set a password for anything beyond local development.
- nginx forwards `X-Forwarded-For` and `X-Real-IP`. The rate limiter and the login lock count the address that Laravel reports, so trust proxy settings decide whether that is the client or nginx. Review `TrustProxies` before exposing the stack.
- `X-Served-By` and `X-Upstream` reveal instance names and internal addresses. They help the load test and should be removed in production.

## Channels

| Channel | Protection |
| --- | --- |
| `products`, `stock` | Public. They carry catalog data and quantities that the public API already exposes |
| `orders.user.{id}` | Private. Authorized only when the JWT user id equals `{id}` |
| `orders.admin` | Private. Authorized only for users with the `admin` role |
| `cart.{id}` | Private. Authorized only when the JWT user id equals `{id}` |

`/api/broadcasting/auth` runs behind `auth:api` and the `active` middleware, so a disabled or locked account cannot subscribe. Authorization uses the guard `api`, the same JWT as the REST API.

## Browser origins

`CORS_ALLOWED_ORIGINS` limits which origins may call the API, and `REVERB_ALLOWED_ORIGINS` limits which origins may open a WebSocket to Reverb. Set both in any shared environment. `CORS_ALLOWED_ORIGINS` takes full origins such as `http://localhost:5173`. `REVERB_ALLOWED_ORIGINS` takes host names only, such as `localhost,127.0.0.1` or `shop.example.com`, because Reverb compares the host of the `Origin` header and ignores the scheme and the port. A full origin there makes Reverb refuse every connection.

## Tokens in the browser

The client keeps the JWT in `localStorage`, as the Flutter client of requirement 1 keeps it in local storage. It is simple and survives reloads, and it is readable by any script that runs on the page, so a cross-site scripting flaw would expose it. The React code renders data through React, which escapes it, and uses no `dangerouslySetInnerHTML`. A production deployment should add a content security policy and consider short-lived tokens with refresh in an HTTP-only cookie.

## Messaging

- Malformed or invalid stock events never reach the browsers. They are rejected into the dead letter queue.
- The stock consumer broadcasts only the fields it validated, not the raw message.
- Mail jobs carry the order id, the mail kind and the recipient address. They hold no passwords or tokens, and the mail body is built from the database when the job runs.
