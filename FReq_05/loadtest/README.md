# Load tests

> Part of the [ECommerce](../../README.md) repository, requirement 5 ([FReq_05](../README.md)). [Documentation index](../docs/README.md) · [Testing](../docs/testing.md) · [Reliability](../docs/reliability.md)

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/k6-load%20tests-7D64FF?logo=k6&logoColor=white" alt="k6">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../../FReq_04/README.md) · [Requirement 5](../README.md)

The k6 scripts of requirements 2 and 3 are in [FReq_02_03/loadtest](../../FReq_02_03/loadtest/README.md). The comparison of sync and async from requirement 4 is `orders.js`. Requirement 5 adds the scripts that show distribution across instances. What each run is expected to show and how to read it is in [load-testing.md](../docs/load-testing.md).

| Script | Requirement | Purpose |
| --- | --- | --- |
| `orders.js` | 4 | Places orders on one processing mode and records the response time of the order request |
| `scale.js` | 5 | Sends 500 requests at a constant rate over 120 seconds and counts the answers per instance from `X-Served-By` |
| `cart.js` | 5 | Adds to a cart and reads it repeatedly through nginx, and fails if the cart differs between reads |
| `mail.js` | 5 | Reads Mailpit and fails if any recipient got the same subject twice |
| `failover.ps1`, `failover.sh` | 5 | Runs `scale.js`, stops an instance after 60 seconds and starts it again after 100 |
| `lib.js` | 5 | Shared helpers: login, authorized headers, product creation, instance counting |

## Requirement 5 scripts

```powershell
docker compose --profile tools run --rm k6 run /scripts/scale.js
```

```powershell
docker compose --profile tools run --rm -e WORKLOAD=order k6 run /scripts/scale.js
```

```powershell
docker compose --profile tools run --rm k6 run /scripts/cart.js
```

```powershell
docker compose --profile mail --profile tools run --rm -e WAIT_SECONDS=30 k6 run /scripts/mail.js
```

```powershell
.\loadtest\failover.ps1
```

```sh
sh loadtest/failover.sh
```

| Variable | Used by | Default | Meaning |
| --- | --- | --- | --- |
| `BASE_URL` | all | `http://localhost:8000` (Compose sets `http://nginx:8000`) | Address of nginx |
| `MODE` | all | `async` | `sync` or `async` |
| `INSTANCES` | `scale.js` | `app1,app2,app3` | Names expected in `X-Served-By` |
| `TOTAL`, `SECONDS` | `scale.js` | 500, 120 | Number of requests and length of the run |
| `WORKLOAD` | `scale.js` | `read` | `read` or `order` |
| `FAIL_RATE` | `scale.js` | 0.01 | Largest accepted share of failed requests |
| `MIN_SHARE` | `scale.js` | 0 | Smallest expected share of one instance, in percent. Reported, not enforced |
| `PRE_VUS`, `MAX_VUS` | `scale.js` | 20, 100 | Virtual users reserved and allowed |
| `READS`, `ITERATIONS` | `cart.js` | 8, 25 | Reads per round and rounds per user |
| `WAIT_SECONDS`, `MAILPIT_URL` | `mail.js` | 30, `http://localhost:8025` | Wait for the workers, address of Mailpit |
| `INSTANCE`, `STOP_AFTER`, `START_AFTER`, `TOTAL`, `SECONDS_TOTAL`, `WORKLOAD` | failover scripts | `app2`, 60, 100, 500, 120, `read` | Which instance, when to stop and start it, and the load |

`scale.js` prints a table of requests and share per instance and writes `loadtest/results/<LABEL>.json`. k6 fails the run when the error rate is above `FAIL_RATE` or fewer than 95 percent of the checks pass. With `MIN_SHARE` (a percentage, for example 20) the summary also prints `distribution check: failed for <instance>` for every instance below that share. That line does not change the exit code, so read it. The folder `results` holds the files of the runs recorded in [load-testing.md](../docs/load-testing.md#results-observed): `scale-read-async.json`, `scale-order-async.json`, `failover-read.json` and `cart.json`. The login token that k6 keeps as setup data is removed before a result file is written.

## Parameters of `orders.js`

| Variable | Default | Meaning |
| --- | --- | --- |
| `BASE_URL` | `http://localhost:8000` (the compose file sets `http://nginx:8000`) | API address, through nginx |
| `MODE` | `async` | `sync` or `async` |
| `LABEL` | `<MODE>-run` | Name of the run, used in the result file name |
| `RESULTS_DIR` | `/results` | Folder for the JSON result |
| `VUS`, `DURATION` | 10, `45s` | Virtual users and run length |

## Run with Docker

```powershell
docker compose run --rm -e MODE=sync -e LABEL=sync k6 run /scripts/orders.js
```

```powershell
docker compose run --rm -e MODE=async -e LABEL=async k6 run /scripts/orders.js
```

The results are written to `loadtest/results/sync.json` and `loadtest/results/async.json`, and a one-line summary is printed.

## Run on the host

```powershell
k6 run -e MODE=sync -e LABEL=sync -e RESULTS_DIR=loadtest/results loadtest/orders.js
```

```powershell
k6 run -e MODE=async -e LABEL=async -e RESULTS_DIR=loadtest/results loadtest/orders.js
```

## Reading the result

Compare `latency_place_order` (median and 95th percentile) and `orders_created`. The async path should answer faster when sending the mail is slow, because the request no longer waits for it. With `MAIL_MAILER=log` the mail is almost free, so the difference is small. To see the effect clearly, use an SMTP server with a real delay, for example Mailpit behind a delaying proxy or a real provider. No measurement has been run yet, so this folder holds no results and the repository claims none.

---

## License

This project is released under the [MIT License](../../LICENSE). Copyright (c) 2026 Majd AlDakhoul.
