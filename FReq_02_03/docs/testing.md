# Testing

> [Documentation index](README.md) · [Local set-up](local-setup.md) · [Docker guide](docker.md) · [Verification status](verification-status.md)

| Layer | Tool | Needs | Command location |
| --- | --- | --- | --- |
| Laravel unit and feature tests | PHPUnit | PHP only | `ECBackend` |
| Go unit tests | `go test` | Go only | `ECInventory` |
| Go integration tests | `go test -tags integration` | Real MySQL | `ECInventory` |
| HTTP race test | k6 | Running stack and k6 | `FReq_02_03` |
| Performance tests | k6 and lab API | Running stack, large data set | `FReq_02_03` |
| Manual flows | Postman | Running stack | any |

## Laravel

```powershell
php artisan test
```

The suite uses in-memory SQLite and replaces the gRPC gateway with `tests/Support/FakeInventoryGateway.php`, so it needs neither MySQL nor the Go service. The recorded result is 148 tests passing. The coverage table is in the [backend README](../ECBackend/README.md#testing).

Run a subset:

```powershell
php artisan test --filter=Performance
```

```powershell
php artisan test --filter=InventoryIntegrationTest
```

## Go unit tests

```powershell
go test ./internal/...
```

With the race detector, on a machine that has a C compiler:

```powershell
go test -race ./internal/...
```

| Package | Proves |
| --- | --- |
| `internal/service` | Validation, merging of duplicate items, all-or-nothing reservation, shortage reasons, idempotent replay, release exactly once, retry on a duplicate insert |
| `internal/seed` | Quantities stay in range, the same seed gives the same quantities, an empty product list is refused |
| `internal/config` | Defaults and environment overrides |

## Inventory race proofs

Two independent proofs exist, one at the data layer and one end to end.

### Integration tests on real MySQL

Set the connection, then run from `ECInventory`:

```powershell
$env:DB_USER="root"; $env:DB_PASSWORD=""; $env:DB_NAME="inventory"
```

```powershell
go test -tags integration -count=1 -v ./tests/integration/...
```

| Test | Expectation |
| --- | --- |
| Ten concurrent reservations for stock 1 | Exactly one succeeds |
| Release | Stock is restored once, a second release changes nothing |
| Eight identical concurrent reservations | Stock decrements once |

Without the environment variables the test retries the database connection for about 30 seconds and appears to hang.

### k6 end to end

```powershell
k6 run -e BASE_URL=http://localhost:8000 loadtest/race.js
```

Ten simultaneous HTTP orders for a product with stock 1. Required: `orders_created` is 1, `orders_conflict` is 9, `orders_unexpected` is 0. The script is described in the [load test guide](../loadtest/README.md).

## Performance tests

The ordered procedure, the variables to keep constant, and the recorded numbers are in [PERFORMANCE.md](../PERFORMANCE.md). Short form:

| Script | Question it answers |
| --- | --- |
| `compare.js` | How do p50, p95, p99 and throughput change between baseline and each optimization |
| `consistency.js` | Does an update appear in the next read, with zero stale reads |
| lab `compare` endpoint | In-process timing of the same operation with the optimization on and off |
| lab `explain` endpoint | Which index a query uses and how many rows it reads |

Always run k6 from the `FReq_02_03` folder so the script paths resolve.

## Manual verification flows

The Postman collections cover these flows, each with the expected result.

| Flow | Expected |
| --- | --- |
| Product list twice | First `X-Cache: MISS`, then `HIT` |
| Repeat with `If-None-Match` equal to the ETag, including the quotes | `304`, empty body |
| Flush cache, then list | `MISS` |
| Update a product, then list | New value immediately |
| Order more than the stock | `409` |
| Stop the inventory service, then order | `503` |
