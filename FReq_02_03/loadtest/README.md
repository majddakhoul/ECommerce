# Load tests

> Part of [FReq_02_03](../README.md). [Documentation index](../docs/README.md) · [PERFORMANCE.md](../PERFORMANCE.md) · [Testing](../docs/testing.md)

k6 scripts for requirements 2 and 3. Run them from the `FReq_02_03` folder, so the script paths resolve.

| Script | Requirement | Purpose |
| --- | --- | --- |
| `compare.js` | 3 | Mixed read traffic (list, search, detail, category). Run it with `VERSION=v2` and `VERSION=v3` and compare the exported JSON files. |
| `consistency.js` | 3 | Updates a product and reads it back immediately, repeatedly. The expected number of stale reads is zero. With `EXPECT_STALE=true` and invalidation switched off it shows the failure that invalidation prevents. |
| `race.js` | 2 | Ten concurrent orders for a product with stock 1. Expects one `201` and nine `409`. |

## Parameters

| Variable | Used by | Default | Meaning |
| --- | --- | --- | --- |
| `BASE_URL` | all | `http://localhost:8000` (the compose file sets `http://app:8000`) | API address |
| `VERSION` | `compare.js` | `v2` | `v2` or `v3` |
| `LABEL` | `compare.js` | `<VERSION>-run` | Name of the run, used in the result file name |
| `RESULTS_DIR` | `compare.js` | `/results` | Folder for the JSON result |
| `VUS`, `DURATION` | `compare.js` | 20, `60s` | Virtual users and run length |
| `ROUNDS` | `consistency.js` | 200 | Update-and-read rounds |
| `EXPECT_STALE` | `consistency.js` | `false` | Expect stale reads, for the invalidation-off run |
| `CONCURRENCY` | `race.js` | 10 | Simultaneous orders |

## Run without Docker

```powershell
k6 run -e BASE_URL=http://localhost:8000 -e RESULTS_DIR=loadtest/results -e VERSION=v2 -e LABEL=step0-v2-baseline loadtest/compare.js
```

```powershell
k6 run -e BASE_URL=http://localhost:8000 loadtest/race.js
```

## Run with Docker

```powershell
docker compose --profile tools run --rm k6 run -e VERSION=v2 -e LABEL=step0-v2-baseline /scripts/compare.js
```

```powershell
docker compose --profile tools run --rm k6 run /scripts/race.js
```

Results land in `loadtest/results`. The ordered procedure and the tables to fill are in [PERFORMANCE.md](../PERFORMANCE.md).

## Accounts

`race.js` and `consistency.js` log in with the demo accounts `admin@example.com` and `user@example.com`, password `password`, so the database must be seeded.
