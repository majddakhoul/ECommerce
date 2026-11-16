# Troubleshooting

> [Documentation index](README.md) · [Local set-up](local-setup.md) · [Docker guide](docker.md) · [Testing](testing.md)

Each entry is a problem that occurred while building and measuring this project, with its cause and fix.

## Go inventory service

| Symptom | Cause | Fix |
| --- | --- | --- |
| `go run` asks for `go mod tidy` | `go.sum` is not committed | Run `go mod tidy` once in `ECInventory` |
| `package ecinventory/gen/inventorypb` not found | Generated code is not committed | Run `make proto`, see [local set-up](local-setup.md#2-inventory-service) |
| `Access denied for user 'inventory'` | A new terminal lost the environment variables | Set `DB_USER`, `DB_PASSWORD`, `DB_NAME` again in that terminal |
| Integration test seems to hang for 30 seconds | Missing environment variables, so the connection retries | Set them and add `-v` |
| `go test -race` fails in the Docker build image | No C compiler there | Use `go test` in Docker and `-race` on the host |
| Seeder says there are no products | The application database is empty or `APP_DB_NAME` is wrong | Migrate and seed Laravel first, set `APP_DB_NAME` |

## Laravel

| Symptom | Cause | Fix |
| --- | --- | --- |
| Flag update returns `500` after switching databases | The token belongs to the previous database | Log in again |
| `409` or `503` on every order | Inventory service down or wrong key | Check the service, `INVENTORY_API_KEY` on both sides |
| Products show stock 0 after seeding | The inventory was seeded before the final migration | Re-run `go run ./cmd/seed` last |
| `X-Data-Source` shows `mysql` | Expected. The value names the store that served the read. | No action |
| Changes in `.env` are ignored | Cached configuration | `php artisan config:clear` |
| `php artisan test` not found in the Docker `app` container | Development packages are not installed in the image | Run the tests on the host |

## Postman

| Symptom | Cause | Fix |
| --- | --- | --- |
| `EISDIR: illegal operation on a directory` on a PUT | The body is form-data with a file field pointing at a folder | Use a raw JSON body |
| `304` never appears | `If-None-Match` lacks the quotes of the ETag | Copy the ETag exactly, quotes included |
| `Unauthenticated` after a restart | Token expired | Log in again |

## Performance measurement

| Symptom | Cause | Fix |
| --- | --- | --- |
| k6 reports `moduleSpecifier couldn't be found` | Run from the wrong folder | Run from `FReq_02_03` |
| k6 reports `unknown shorthand flag 'M'` | Two commands were pasted on one line | Run each command separately |
| k6 cannot write `/results/...json` | The default result folder is a Docker path | Pass `-e RESULTS_DIR=loadtest/results` |
| `PHP_CLI_SERVER_WORKERS` warning | Not supported on Windows | Expected. The built-in server is single-process on Windows. |
| Cache gives no latency gain | File cache and a single-process server dominate the request time | Use the Docker stack with Redis, see [PERFORMANCE.md](../PERFORMANCE.md#findings) |
| `perf:indexes drop` error 1553 | The foreign key needs an index and MySQL dropped it silently when a composite index was added | Fixed in the command, which restores the foreign key index. Update to the current version. |
| Different numbers each run | Cold buffer pool, other programs, throttled API | Keep the [variables constant](../PERFORMANCE.md#variables-that-must-stay-the-same-between-runs), warm up, repeat |

## Docker

| Symptom | Cause | Fix |
| --- | --- | --- |
| Port already in use | Another MySQL or Redis on the host | Change the port in `.env` |
| `APP_KEY` error from Compose | `.env` is missing | Copy `.env.example` to `.env` |
| First build very slow | The PHP gRPC extension compiles from source | Wait, or rebuild with the cache |
| `app` restarts in a loop | Database or inventory not healthy yet, or migration failure | `docker compose logs app` |
| Replica not replicating | `replica-setup` did not run or failed | `docker compose logs replica-setup`, then re-run it with `docker compose up replica-setup` |
| Everything stale after changing data by hand | Redis still holds cache | Call the cache flush endpoint or `docker compose restart redis` |

The Docker rows are written from the configuration and have not been confirmed by a run.
