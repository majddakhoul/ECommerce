# Security

> [Documentation index](README.md) · [Architecture](architecture.md) · [Docker guide](docker.md)

The API-level controls of requirement 1 (JWT, lockout, rate limiting, roles, headers) are described in the [backend README](../ECBackend/README.md#security-features-implemented). This document covers the parts that belong to requirements 2 and 3 and the handling of secrets.

## Service-to-service

| Control | Detail |
| --- | --- |
| Authentication | Every gRPC call except `Ping` must carry `x-api-key`. A Go interceptor rejects missing or wrong keys with `UNAUTHENTICATED`. |
| Network exposure | In Docker, the inventory database is published on a host port for development convenience only. Remove the `ports` entries of the databases for any shared deployment. |
| Deadline | Laravel gives every call a 2 second deadline, so a slow service cannot hold requests open. |
| Failure mapping | All gRPC failures become `503`, so internals are not exposed to clients. |
| Privileges | The inventory container runs as the unprivileged user `inventory`. |

An empty `INVENTORY_API_KEY` disables the check. Use that only for local experiments.

## Lab endpoints

All `/api/v3/performance/*` routes require the `admin` role. They can flush the cache, switch flags, and run queries, so they must not be exposed to ordinary users.

## Secrets

- `.env` files are never committed. The repository ignores them, and the packaged archives exclude them.
- `.env.example` files hold development values only. `APP_KEY`, `JWT_SECRET`, database passwords and `INVENTORY_API_KEY` must be replaced for any real deployment.
- If a `.env` file was ever shared or committed, treat every value in it as exposed: rotate the mail provider app password, regenerate `APP_KEY` with `php artisan key:generate`, and regenerate `JWT_SECRET` with `php artisan jwt:secret`. Rotating `APP_KEY` invalidates encrypted data and sessions, and rotating `JWT_SECRET` signs everyone out.

## Data integrity

- Stock cannot go negative or be oversold because the check and the decrement are one SQL statement inside a transaction.
- Reservations are idempotent by `reservation_id`, so a retry after a timeout cannot double-decrement.
- A release is applied once, so a cancel retried by the client cannot inflate the stock.
- Cached data never includes stock, so the cache cannot show a quantity that an order has already taken.
- With the read replica on, a caller who just wrote reads from the primary for `PERF_REPLICA_STICKY_SECONDS`, so they do not see their own write missing.

## Known weaknesses

- Development defaults (`APP_DEBUG=true`, shared database passwords, a fixed replication password) are unsafe outside a lab.
- The gRPC channel is not encrypted. Add TLS before sending traffic across an untrusted network.
- The cache has no stampede protection, see [PERFORMANCE.md](../PERFORMANCE.md#known-limits).
