# Verification status

> [Documentation index](README.md) · [Testing](testing.md) · [Docker guide](docker.md) · [PERFORMANCE.md](../PERFORMANCE.md)

This file separates what was executed from what was only written. Evidence is the output of the commands, produced on one Windows machine with XAMPP MySQL, PHP, Go and Windows PowerShell 5.1.

## Requirement 2

| Item | Status | Evidence |
| --- | --- | --- |
| gRPC contract and generated code | Verified | Go service built and served calls, Laravel called it |
| Go unit tests (service, seed, config) | Passed | `go test ./internal/...` |
| Go integration tests on real MySQL | Passed | Three tests, see [testing.md](testing.md#integration-tests-on-real-mysql) |
| Laravel test suite | Passed | 148 tests |
| Order with insufficient stock returns `409` | Verified | Postman, cart checkout and order flows |
| Service stopped returns `503` | Verified | Postman |
| Race condition, ten concurrent orders for stock 1 | Passed | `race.js`: one created, nine conflicts, none unexpected |
| Release on failed save and on cancellation | Verified | Automated tests, and the Postman cancel flow |
| Go seeder | Verified | Ran against the real databases |

## Requirement 3

| Item | Status | Evidence |
| --- | --- | --- |
| Cache `MISS` then `HIT` | Verified | Response headers in Postman |
| ETag and `304` | Verified | Postman, with the ETag quoted exactly |
| Update visible immediately | Verified | Postman, and `consistency.js` with zero stale reads |
| Indexes, FULLTEXT, `EXPLAIN`, N+1 comparison | Measured | [Results](../PERFORMANCE.md#results) |
| k6 steps 0 to 2 | Measured | Single runs, see the table in PERFORMANCE.md |
| Stale reads with invalidation off | Measured | 49 of 50 |
| Redis as the cache store | Not run | The local set-up used the file store |
| Read replica, step 3 | Not run | Needs the Docker stack |
| Multi-process PHP server | Not run | Not supported by PHP on Windows |

## Docker

| Item | Status |
| --- | --- |
| `docker-compose.yml`, Dockerfiles, entrypoint, replica scripts | Written and reviewed, never built or started |
| `docker compose up --build` | Not executed |
| Container health checks and start order | Not executed |
| GTID replication between `db` and `db-replica` | Not executed |
| `inventory-tests` and `k6` services | Not executed. The same tests and scripts were run on the host. |
| PHP gRPC extension compilation in the image | Not executed |

Consequences: any first-run problem in Docker is not covered by evidence here. The host runs prove the application and Go code, the contract, the SQL, and the cache logic, but not the container packaging.

## Other components

| Item | Status |
| --- | --- |
| Flutter client of requirement 1 | Untouched and not retested here |
| Git upload and merge request | Not applicable |
| Real SMTP email delivery | Not verified in this work |

## Limits of the measurements

- One run per step, so differences of about 10 to 20 percent are noise.
- Step 0 ran once on a cold buffer pool and on the v2 routes, step 1 on the v3 routes, so the improvement is not attributable to the index alone.
- The cache did not lower the tail latency on this set-up, because fixed per-request costs dominate. The reasons are in [Findings](../PERFORMANCE.md#findings).
- The absolute throughput (about 3 requests per second) is a property of the single-process server on Windows and is not representative of a production set-up.

## How to close the gaps

1. Run the stack as described in [docker.md](docker.md) and note the first failure, if any.
2. Run `php artisan perf:seed`, then [PERFORMANCE.md](../PERFORMANCE.md) from step 0 to step 5 inside Docker.
3. Record step 3 and the Redis numbers in the results tables, then update this file.
