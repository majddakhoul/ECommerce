# Load tests

> Part of [FReq_02_03](../README.md). [Documentation index](../docs/README.md) · [PERFORMANCE.md](../PERFORMANCE.md) · [Testing](../docs/testing.md)

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter&logoColor=white" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/k6-load%20tests-7D64FF?logo=k6&logoColor=white" alt="k6">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../README.md) · [Requirement 4](../../FReq_04/README.md)

The sync against async measurement of requirement 4 is in [FReq_04/loadtest/README.md](../../FReq_04/loadtest/README.md).

k6 scripts for requirements 2 and 3. Run them from the `FReq_02_03` folder, so the script paths resolve.

| Script | Requirement | Purpose |
| --- | --- | --- |
| `compare.js` | 3 | Mixed read traffic (list, search, detail, category). Run it with `VERSION=v2` and `VERSION=v3` and compare the exported JSON files. |
| `consistency.js` | 3 | Updates a product and reads it back immediately, repeatedly. The expected number of stale reads is zero. With `EXPECT_STALE=true` and invalidation switched off it shows the failure that invalidation prevents. |
| `race.js` | 2 | Ten concurrent orders for a product with stock 1. Expects one `201` and nine `409`. |

---

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

---

## Run without Docker

```powershell
k6 run -e BASE_URL=http://localhost:8000 -e RESULTS_DIR=loadtest/results -e VERSION=v2 -e LABEL=step0-v2-baseline loadtest/compare.js
```

```powershell
k6 run -e BASE_URL=http://localhost:8000 loadtest/race.js
```

---

## Run with Docker

```powershell
docker compose --profile tools run --rm k6 run -e VERSION=v2 -e LABEL=step0-v2-baseline /scripts/compare.js
```

```powershell
docker compose --profile tools run --rm k6 run /scripts/race.js
```

Results land in `loadtest/results`. The ordered procedure and the tables to fill are in [PERFORMANCE.md](../PERFORMANCE.md).

---

## Accounts

`race.js` and `consistency.js` log in with the demo accounts `admin@example.com` and `user@example.com`, password `password`, so the database must be seeded.

---

## License

This project is released under the [MIT License](../../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
