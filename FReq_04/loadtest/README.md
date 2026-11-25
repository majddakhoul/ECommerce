# Load tests

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)). [Documentation index](../docs/README.md) · [Testing](../docs/testing.md) · [Reliability](../docs/reliability.md)

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/RabbitMQ-3.13-FF6600?logo=rabbitmq&logoColor=white" alt="RabbitMQ 3.13">
<img src="https://img.shields.io/badge/k6-load%20tests-7D64FF?logo=k6&logoColor=white" alt="k6">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md) · [Requirement 5](../../FReq_05/README.md)

The k6 scripts of requirements 2 and 3 are in [FReq_02_03/loadtest](../../FReq_02_03/loadtest/README.md). This folder holds the script of requirement 4.

| Script | Purpose |
| --- | --- |
| `orders.js` | Places orders on one processing mode and records the response time of the order request |

The script logs in as the administrator, creates a product with a very large stock, and then has every virtual user place one-unit orders for it. Run it once per mode, with the same settings, and compare the two result files.

## Parameters

| Variable | Default | Meaning |
| --- | --- | --- |
| `BASE_URL` | `http://localhost:8000` (the compose file sets `http://app:8000`) | API address |
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
