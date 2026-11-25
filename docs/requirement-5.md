# Requirement 5: scalability and load distribution

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

Requirement 5 lives in [FReq_05](../FReq_05/README.md). It runs the platform of [requirement 4](requirement-4.md) as three Laravel instances and three queue workers behind an nginx load balancer, with one shared Redis, so that a request, a cart, a lock or a mail job behaves the same whichever instance or worker takes it. The proof is a set of k6 tests: 500 requests over two minutes spread across the instances, a run in which one instance is stopped, a cart read from every instance, and an audit that no mail is delivered twice.

## What it contains

| Part | Folder | Documentation |
| --- | --- | --- |
| nginx 1.30.2 with `least_conn` and passive health checks | [docker/nginx](../FReq_05/docker/nginx/nginx.conf) | [scaling.md](../FReq_05/docs/scaling.md) |
| Compose stack: three instances, three workers, consumer, Reverb, web, RabbitMQ, two MySQL, Redis, Go service, Mailpit and k6 profiles | [docker-compose.yml](../FReq_05/docker-compose.yml) | [docker.md](../FReq_05/docs/docker.md) |
| Laravel API, atomic `OnceGuard`, per-user cart lock, `X-Served-By` header | [ECBackend](../FReq_05/ECBackend/README.md) | [Backend README](../FReq_05/ECBackend/README.md) |
| Go inventory service | [ECInventory](../FReq_05/ECInventory/README.md) | [Inventory README](../FReq_05/ECInventory/README.md) |
| React web client | [ECWeb](../FReq_05/ECWeb/README.md) | [Web README](../FReq_05/ECWeb/README.md) |
| k6 scripts `scale.js`, `cart.js`, `mail.js`, `failover.ps1`, `failover.sh`, `orders.js` | [loadtest](../FReq_05/loadtest/README.md) | [load-testing.md](../FReq_05/docs/load-testing.md) |
| Cross-cutting documents | [docs](../FReq_05/docs/README.md) | [Architecture](../FReq_05/docs/architecture.md), [Scaling](../FReq_05/docs/scaling.md), [Configuration](../FReq_05/docs/configuration.md), [Reliability](../FReq_05/docs/reliability.md), [Compliance](../FReq_05/docs/compliance.md), [Testing](../FReq_05/docs/testing.md), [Security](../FReq_05/docs/security.md), [Troubleshooting](../FReq_05/docs/troubleshooting.md), [Verification status](../FReq_05/docs/verification-status.md) |

## Prerequisites

Git and Docker Desktop. Nothing else is needed for the Docker path, see [environment-setup.md](environment-setup.md). Ports 5173, 8000, 8080, 5672, 15672, 50051, 3307, 3308 and 6379 must be free, and 8025 for the mail audit. Stop any other stack first, see [the port table](environment-setup.md#ports-used-by-each-stack).

## Run with Docker

From `FReq_05` in PowerShell.

1. Create the environment file:

```powershell
Copy-Item .env.example .env
```

2. Generate three secrets, running this command once for each:

```powershell
docker run --rm php:8.3-cli php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

3. Fill the empty values of `.env`:

| Variable | Value |
| --- | --- |
| `APP_KEY` | `base64:` followed by a generated value |
| `JWT_SECRET` | A generated value |
| `REVERB_APP_KEY` | Any random string. The web image is built with it |
| `REVERB_APP_SECRET` | Any random string |

4. Build and start:

```powershell
docker compose up --build
```

The first build compiles the PHP `grpc` extension, which takes 15 to 40 minutes. Docker may print `[output clipped, log limit 2MiB reached]`, which only means the log was cut. The layer is cached, so later builds take seconds. Do not use `--no-cache`.

5. Wait until the output settles, then in another PowerShell window:

```powershell
docker compose ps
```

Three `app` containers, `nginx`, `reverb`, `web` and the databases must be healthy, and three `worker` containers and `consumer` must be up. `migrate` has exited with code 0, which `docker compose ps -a` shows.

6. Open `http://localhost:5173`. Demo accounts, password `password`: `admin@example.com` and `user@example.com`. The API entry point is `http://localhost:8000`, RabbitMQ is on `http://localhost:15672`, and the Queues tab shows three consumers on `emails`.

To start in the background use `docker compose up -d`. Details of every container, the start order and the web image arguments are in [docker.md](../FReq_05/docs/docker.md).

## Stop and reset

```powershell
docker compose --profile mail --profile tools down
```

The profiles are named so that Mailpit and any finished k6 container are stopped too. Without them, `docker compose down` leaves Mailpit running and prints `Network freq_05_default Resource is still in use`, which is harmless.

```powershell
docker compose --profile mail --profile tools down -v
```

The second command also deletes the databases, Redis data and uploaded images.

## Prove the distribution

All k6 commands run from `FReq_05`. The scripts reach the stack through nginx, inside the Docker network.

### 500 requests in 2 minutes

```powershell
docker compose --profile tools run --rm k6 run /scripts/scale.js
```

It sends 500 requests at a fixed arrival rate over 120 seconds and counts which instance answered, using the `X-Served-By` header. It prints the table, the percentage of failed requests, and writes `loadtest/results/scale-read-async.json`. The recorded run: 167 requests on each of `app1`, `app2` and `app3`, none without an instance header, no failed request. See [results.md](results.md#requirement-5).

Orders instead of reads:

```powershell
docker compose --profile tools run --rm -e WORKLOAD=order -e TOTAL=50 -e SECONDS=30 k6 run /scripts/scale.js
```

### Stop an instance during the run

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

```powershell
.\loadtest\failover.ps1
```

The first command allows the script to run in the current window only. The script starts `scale.js`, stops `app2` after 60 seconds, starts it again after 100 seconds and waits for k6 to finish. On a shell with `sh`, run `sh loadtest/failover.sh` instead. The recorded run: `app1` 197, `app2` 104, `app3` 198 and one request without an instance header, which is consistent with the request that was in progress on `app2` when it stopped.

### The cart is the same on every instance

```powershell
docker compose --profile tools run --rm k6 run /scripts/cart.js
```

Two users repeat: clear the cart, add an item, read the cart eight times. The add and the reads reach different instances through nginx. The recorded run: 400 reads, `app1` 134, `app2` 132, `app3` 134, and `cart_mismatch` 0.

### No mail is delivered twice

This test needs a mail catcher, so Mailpit and the SMTP driver must be on.

1. In `FReq_05\.env` set:

```text
MAIL_MAILER=smtp
```

2. Restart the stack with the mail profile, so the setting reaches the containers:

```powershell
docker compose --profile mail --profile tools down
```

```powershell
docker compose --profile mail up -d
```

3. Confirm the driver inside a container. The three lines must be `smtp`, `mailpit` and `1025`:

```powershell
docker compose exec app1 printenv MAIL_MAILER MAIL_HOST MAIL_PORT
```

4. Clear old messages, so the audit counts only this run:

```powershell
Invoke-RestMethod -Method Delete http://localhost:8025/api/v1/messages
```

5. Place 50 orders:

```powershell
docker compose --profile tools run --rm -e WORKLOAD=order -e TOTAL=50 -e SECONDS=30 k6 run /scripts/scale.js
```

6. Audit:

```powershell
docker compose --profile mail --profile tools run --rm -e WAIT_SECONDS=30 k6 run /scripts/mail.js
```

Each order produces two mails, one to the customer and one to the administrator, and each subject contains the order number. The audit fails if two messages share a recipient and a subject, and it also fails when no message arrived, so a run with the `log` driver cannot pass. The recorded run: 51 orders, 102 messages, 102 distinct, 0 duplicated. Open `http://localhost:8025` to see the mails.

With the default `MAIL_MAILER=log` the mails are written to `storage/logs/laravel.log` of each worker and Mailpit stays empty, which is the reason the audit shows nothing in that case.

## Run without Docker

Several copies of the API behind your own nginx, a shared cache and the workers started by hand are described in [local-setup.md](../FReq_05/docs/local-setup.md#several-instances-without-docker). The set-up of the inventory service, RabbitMQ and the databases is the same as in [requirement 4](requirement-4.md#run-locally).

## Test

From `FReq_05/ECBackend`:

```powershell
php artisan test
```

The suite has 187 tests, 12 of them new in `tests/Feature/V5`: the atomic guard, concurrent delivery of mail and stock events, the cart lock and the instance header. They use an array cache, which implements the same lock interface as Redis, so they prove the logic and cannot prove that two real processes compete correctly. The k6 runs above are the evidence for that part.

From `FReq_05/ECInventory`:

```powershell
go test -race ./internal/...
```

From `FReq_05/ECWeb`:

```powershell
npm test
```

The test table is in [testing.md](../FReq_05/docs/testing.md).

## Postman

Import [`Requirement-5-v5.postman_collection.json`](../FReq_05/ECBackend/postman/Requirement-5-v5.postman_collection.json) from `FReq_05/ECBackend/postman`. It has 61 requests: the earlier flows through nginx and the folder `Requirement 5 - Scaling and load distribution`, which counts the instances that answered in the variable `instanceCounts`, checks the cart across instances, and reads the RabbitMQ queues and Mailpit. Set `baseUrl` to `http://localhost:8000`. The collection was generated by a script and validated as JSON. It has not been run in Postman, see [results.md](results.md#not-demonstrated).

## What is not shown

| Item | Status |
| --- | --- |
| Redis restart keeps the cart, the locks and the markers | Not run. Redis runs with append-only persistence and `noeviction` |
| Several Docker hosts | Not run. A named volume for images works on one host only |
| Real SMTP delivery to Gmail | Not run. The mail audit used Mailpit, a local SMTP catcher |
| A throughput gain | Not claimed. Three containers on one machine share its processor, so the test shows distribution and resilience |

The single points of failure that remain are nginx, Redis, the two databases, RabbitMQ, the Go service and Reverb, listed in [scaling.md](../FReq_05/docs/scaling.md). `php artisan serve` is a development server, used here with `PHP_CLI_SERVER_WORKERS`.

## Continue

[results.md](results.md) gathers every result of the five requirements.
