# Load testing

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



The k6 scripts of requirement 5 prove that requests are spread across the instances, that the cart is the same on every instance, that no mail is sent twice, and that the platform survives the loss of an instance. They are in [`loadtest`](../loadtest/README.md). This page says what each run is expected to show, how to read it, and records the runs that were executed on the author's machine, see [Results observed](#results-observed).

## The five scripts

| Script | What it proves | Pass condition |
| --- | --- | --- |
| `scale.js` | 500 requests in 120 seconds are spread over the three instances | Error rate under 1 percent. The table lists every instance in `INSTANCES` with its count, and with `MIN_SHARE` the summary reports the instances below that share |
| `cart.js` | A cart written through one request is read identically through the others | `cart_mismatch` equals 0 |
| `mail.js` | After orders were placed, no recipient got the same mail twice | No duplicated (recipient, subject) pair in Mailpit |
| `failover.ps1` / `failover.sh` | Stopping one instance in the middle of the run does not stop the service | Error rate under 5 percent, the other instances absorb the load, the instance returns after the restart |
| `orders.js` | The sync against async comparison of requirement 4, now through nginx | See [FReq_04](../../FReq_04/loadtest/README.md) |

## 500 requests over 2 minutes

`scale.js` uses the `constant-arrival-rate` executor: 500 iterations spread evenly over 120 seconds, about 4.2 requests per second, whatever the response time. Each iteration is one request. With the executor `constant-vus` a slow server would receive fewer requests, which hides exactly the problem this test looks for, so the arrival rate is fixed instead.

```powershell
docker compose --profile tools run --rm k6 run /scripts/scale.js
```

| Variable | Default | Meaning |
| --- | --- | --- |
| `TOTAL` | 500 | Number of requests |
| `SECONDS` | 120 | Length of the run |
| `WORKLOAD` | `read` | `read` lists products. `order` places an order |
| `MODE` | `async` | `sync` or `async` |
| `INSTANCES` | `app1,app2,app3` | The names that must appear in `X-Served-By` |
| `FAIL_RATE` | 0.01 | Largest accepted share of failed requests |
| `MIN_SHARE` | 0 | Smallest expected share per instance, in percent, for example 20. It is reported in the summary and does not change the exit code |
| `PRE_VUS`, `MAX_VUS` | 20, 100 | Virtual users reserved and allowed |
| `LABEL` | `scale-<WORKLOAD>-<MODE>` | Name of the result file |

Every response is counted in the metric `served`, tagged with the instance from `X-Served-By`. The summary prints a table:

```text
instance   requests   share
app1       167        33.4%
app2       166        33.2%
app3       167        33.4%
```

The figures above only show the layout of the table, they are not a measurement. With `least_conn` and requests that are almost equally short, the shares are expected to be close to a third each. They do not have to be exactly equal: `least_conn` balances open connections, not the number of requests, so a slower instance can receive slightly fewer.

## Stopping an instance during the run

```powershell
.\loadtest\failover.ps1
```

```sh
sh loadtest/failover.sh
```

The script starts `scale.js`, waits 60 seconds, runs `docker compose stop app2`, waits until second 100, runs `docker compose start app2`, and then waits for k6 to finish. In the result:

| Phase | Seconds | Expected |
| --- | --- | --- |
| Three instances | 0 to 60 | Requests on `app1`, `app2` and `app3` |
| Two instances | 60 to 100 | No request on `app2` after the first few seconds. `app1` and `app3` take about half each |
| Three instances again | 100 to 120 | `app2` serves again once nginx resolves it, within about 5 seconds of its start, plus the time of its health check |

A few requests that were in progress on `app2` at the moment it stopped can fail. The threshold is 5 percent for that reason. The variables are `INSTANCE` (default `app2`), `STOP_AFTER`, `START_AFTER`, `WORKLOAD`, `TOTAL` and `SECONDS_TOTAL`.

## Cart consistency

```powershell
docker compose --profile tools run --rm k6 run /scripts/cart.js
```

Two virtual users, one with the administrator account and one with the user account, repeat: clear the cart, add an item, read the cart `READS` times (default 8). Each read must show the quantity that was added. Because each request goes through nginx and `least_conn`, the add and the reads reach different instances, and a cart kept in the memory of an instance would fail the check.

## Duplicate mails

The audit needs the SMTP driver and Mailpit, because with the default `log` driver nothing reaches Mailpit.

1. Set `MAIL_MAILER=smtp` in `FReq_05/.env`.
2. Restart the stack so the containers read it:

```powershell
docker compose --profile mail --profile tools down
```

```powershell
docker compose --profile mail up -d
```

3. Confirm the driver. The three lines must be `smtp`, `mailpit` and `1025`:

```powershell
docker compose exec app1 printenv MAIL_MAILER MAIL_HOST MAIL_PORT
```

4. Clear the messages of earlier runs:

```powershell
Invoke-RestMethod -Method Delete http://localhost:8025/api/v1/messages
```

5. Place orders:

```powershell
docker compose --profile tools run --rm -e WORKLOAD=order -e TOTAL=50 -e SECONDS=30 k6 run /scripts/scale.js
```

6. Audit:

```powershell
docker compose --profile mail --profile tools run --rm -e WAIT_SECONDS=30 k6 run /scripts/mail.js
```

`mail.js` waits for the workers to finish, reads every message from the Mailpit API and fails if two messages have the same recipient and subject, or if no message arrived. Each order sends two mails, one to the customer and one to the administrator, and the subject contains the order number, so a duplicate means one mail was sent twice. The cause would be the race described in [scaling.md](scaling.md#why-a-lock-and-why-it-must-be-atomic).

## Without k6

`X-Served-By` can be checked with any HTTP client:

```powershell
1..12 | ForEach-Object { (Invoke-WebRequest http://localhost:8000/api/v4/async/products -Headers @{Authorization="Bearer $token"}).Headers['X-Served-By'] }
```

The Postman collection [Requirement-5-v5](../ECBackend/postman/Requirement-5-v5.postman_collection.json) does the same with the Collection Runner and keeps a count per instance in the variable `instanceCounts`.

## Results observed

These runs were executed with `docker compose up --build` on a Windows machine with Docker Desktop, three instances and three workers, `MODE=async`. They are one run each, not averages, and they depend on that machine.

| Run | Command | Result |
| --- | --- | --- |
| 500 requests over 2 minutes, reads | `scale.js` | 501 requests. `app1` 167, `app2` 167, `app3` 167, each 33.3 percent. No request without `X-Served-By`. 0 failed requests of 502. Average 37.3 ms, median 30.7 ms, p95 55.2 ms, p99 85.7 ms |
| 51 order requests over 30 seconds | `scale.js` with `WORKLOAD=order`, `TOTAL=50`, `SECONDS=30` | `app1` 17, `app2` 17, `app3` 17. 51 of 51 orders created, 0 failed requests of 53. Average 96.8 ms, median 64.3 ms, p95 240.9 ms |
| One instance stopped | `failover.ps1`: `app2` stopped at second 60, started again at second 100 | 500 requests. `app1` 197 (39.4 percent), `app2` 104 (20.8 percent), `app3` 198 (39.6 percent), 1 request (0.2 percent) without `X-Served-By`. 1 failed request of 501. Average 39.2 ms, p95 61.0 ms, p99 87.4 ms, maximum 2002 ms. The run passed its thresholds |
| Cart consistency | `cart.js` | 50 iterations, 400 reads: `app1` 134, `app2` 132, `app3` 134, mismatches 0. 450 checks passed, 0 failed, p95 69.0 ms |
| Sync against async | `orders.js` once with `MODE=sync` and once with `MODE=async`, 10 virtual users, about 45 seconds each | Sync: 910 orders, median 490 ms, p95 604 ms, p99 640 ms. Async: 1473 orders, median 303 ms, p95 402 ms, p99 450 ms. No failed request in either run. The async path answered 38 percent faster at the median and handled 32.3 orders per second against 19.9 |
| Duplicate mails | `scale.js` with `WORKLOAD=order`, then `mail.js` with `MAIL_MAILER=smtp` and Mailpit | 51 orders placed, 102 messages in Mailpit (two per order), 102 distinct recipient and subject pairs, 0 duplicated |

Reading the failover run: `app2` served 104 requests instead of about 167 because it was out of the rotation for roughly 40 seconds, and `app1` and `app3` served the difference. The single request without `X-Served-By` is consistent with a request that was in progress on `app2` when it stopped, which is the case described above. No other explanation was investigated.

The two `orders.js` runs were made with the same settings one after the other, and the mail driver of those runs was not recorded. With the `log` driver the mail costs almost nothing, so the gap shown comes mostly from the work that async moves out of the request (the stock events and the broadcasts), and it would grow with a slow SMTP server. Two single runs on one machine show the direction, not an exact gain.

The first mail audit ran with the `log` mail driver, so Mailpit received no message (`messages: 0`) and its `duplicated: 0` proved nothing. `mail.js` now fails when no message arrived. The run in the table above used `MAIL_MAILER=smtp` and shows the real result: every one of the 102 mails was delivered once, although three workers consumed the same queue. The audit used Mailpit, a local SMTP catcher, so no mail went to a real mailbox.

## What the results do not show

- The numbers depend on the machine. Three containers on one laptop share its processor, so the test shows distribution and resilience, not a gain in throughput.
- `least_conn` hides a slow instance, it does not repair it.
- The Compose stack is one Docker host. The behaviour on several hosts is not tested.
