# Scaling and load distribution

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



This page explains how requirement 5 works: what runs, why the instances are interchangeable, how nginx distributes the requests, what each piece of shared state protects against, and which limits remain. The k6 tests that demonstrate it are in [load-testing.md](load-testing.md).

## Contents

- [Topology](#topology)
- [The rule that makes scaling possible](#the-rule-that-makes-scaling-possible)
- [nginx](#nginx)
- [Shared state](#shared-state)
- [Why a lock, and why it must be atomic](#why-a-lock-and-why-it-must-be-atomic)
- [The cart on three instances](#the-cart-on-three-instances)
- [Problems met and how each was solved](#problems-met-and-how-each-was-solved)
- [Failure behaviour](#failure-behaviour)
- [Known limits](#known-limits)

## Topology

| Part | Count | Notes |
| --- | --- | --- |
| nginx 1.30.2 | 1 | Single entry point, port 8000, `least_conn` |
| Laravel API | 3 | `app1`, `app2`, `app3`, the same image, `CONTAINER_ROLE=app`, each with its own `INSTANCE_NAME` |
| Queue workers | 3 | `worker1` to `worker3`, `queue:work rabbitmq --queue=emails,broadcasts` |
| Stock consumer | 1 | `inventory:consume`, reads `ec.stock.changed` |
| Reverb | 1 | The WebSocket server, browsers connect to it directly on 8080 |
| MySQL (API), MySQL (inventory) | 1 each | Shared |
| Redis | 1 | Cache, cart, locks, rate limiter, performance counters |
| RabbitMQ | 1 | Shared by the instances, the workers and the consumer |
| Go inventory service | 1 | The only owner of stock |
| React web client | 1 | Calls the API through nginx |
| `migrate` | 1, one-shot | Runs migrations and seeding once, then exits |

```text
                         +--> app1 --+
browser / k6 --> nginx --+--> app2 --+--> MySQL, Redis, RabbitMQ, Go inventory
   (port 8000)           +--> app3 --+
                                          RabbitMQ --> worker1, worker2, worker3
Go inventory --stock.changed--> RabbitMQ --> consumer --> Reverb --> browsers
```

## The rule that makes scaling possible

An instance must hold nothing that another instance would need. A request can reach any of the three, and the next request of the same user can reach a different one. Every piece of state therefore lives outside the instance:

| Kind of state | Moved to | Setting |
| --- | --- | --- |
| Cache, cart, locks, rate limiting, performance counters | Redis | `CACHE_STORE=redis`, `PERFORMANCE_STORE=redis` |
| Sessions | None. The API is stateless and authenticates with JWT | `SESSION_DRIVER=array` |
| Data | MySQL and the Go service | unchanged |
| Jobs | RabbitMQ | `QUEUE_CONNECTION=rabbitmq` |
| Logs | Standard error of the container | `LOG_CHANNEL=stderr` |
| Secrets | One set of values read by every instance | the `x-backend` anchor in `docker-compose.yml` |

## nginx

The configuration is [`docker/nginx/nginx.conf`](../docker/nginx/nginx.conf).

| Directive | Effect |
| --- | --- |
| `least_conn` | Each request goes to the instance with the fewest open connections. Better than round robin when requests have very different durations, for example a checkout next to a product list |
| `server app1:8000 resolve` (and `app2`, `app3`) with `resolver 127.0.0.11 valid=5s` | nginx asks the Docker DNS again every 5 seconds. A container restarted with a new address is found again without restarting nginx. Needs the `zone laravel_backend 64k` line and nginx 1.27.3 or later in the open-source edition |
| `max_fails=3 fail_timeout=10s` | After three failures an instance is skipped for 10 seconds |
| `proxy_next_upstream error timeout http_502 http_503 http_504`, `tries 2`, `timeout 5s` | A failed attempt is repeated on another instance. A `POST` is never repeated after the request was sent, see problem 10 |
| `proxy_set_header Host $http_host` and the `X-Forwarded-*` headers | The application sees the address the client used and the real client address |
| `keepalive 32` with `proxy_http_version 1.1` | Connections to the instances are reused |
| `add_header X-Upstream $upstream_addr always` | Shows which address nginx chose. The instance name is in `X-Served-By` |
| `location = /nginx-health` | Answered by nginx itself, used by the health check and by the Postman collection |

Your own `nginx.conf` for running outside Docker uses the same `least_conn` upstream over `127.0.0.1:8001` to `8005`. One difference matters: it listens on 8080, and in this project Reverb uses 8080. In Docker nginx listens on 8000 for that reason. Outside Docker, change `listen` to a free port such as 8000. The instances must also share one cache store, see [local-setup.md](local-setup.md#several-instances-without-docker).

## Shared state

| State | Key | Lifetime | Written by |
| --- | --- | --- | --- |
| Cart | `cart:user:{userId}` | 7 days, renewed on every write | any instance |
| Cart lock | `cart:lock:{userId}` | `CART_LOCK_SECONDS` | the instance changing the cart |
| Mail sent marker | `mail-sent:{sha1}` | `JOB_DEDUPE_TTL` | the worker that sent the mail |
| Mail lock | `mail-sent:{sha1}:lock` | `JOB_LOCK_SECONDS` | the worker sending the mail |
| Stock event marker | `stock-event:{event_id}` | `JOB_DEDUPE_TTL` | the consumer |
| Stock event lock | `stock-event:{event_id}:lock` | `RABBITMQ_STOCK_LOCK_SECONDS` | the consumer |

The mail key is the SHA-1 of the order id, the kind of mail, the recipient and the previous status. The same event can reach two workers (a redelivery after a lost acknowledgement, or a retry), and the key identifies it as the same work whichever worker holds it.

## Why a lock, and why it must be atomic

The first version of the de-duplication was:

```text
if the key exists:  stop
send the mail
store the key
```

With one worker this is enough. With three it is not. Two workers can both read "the key does not exist" before either has stored it, and both send the mail. The test and the write are two steps, and another worker can act between them. This is a check-then-act race.

The fix is to make the claim atomic. A lock is a single Redis operation (`SET key value NX EX seconds`): it succeeds for exactly one caller and fails for all the others, with no gap in between. `App\Support\Concurrency\OnceGuard` does this:

```text
if the done-marker exists:        DUPLICATE   (nothing to do)
if the lock is not obtained:      BUSY        (another worker is sending it now)
inside the lock:
    if the done-marker exists:    DUPLICATE   (the other worker finished first)
    run the work
    store the done-marker
release the lock
```

| Outcome | What the caller does |
| --- | --- |
| `EXECUTED` | Done |
| `DUPLICATE` | The mail job ends without sending. The stock event is acknowledged without broadcasting |
| `BUSY` | The mail job is released back to the queue for `JOB_BUSY_RELEASE` seconds and tries again. If the first worker succeeded, the retry becomes `DUPLICATE`. If it crashed, the lock expires after `JOB_LOCK_SECONDS` and the retry sends the mail |

The marker is stored only after the work succeeded. A failed send leaves no marker, so the retry sends it. The lock has an expiry, so a worker that dies while holding it cannot block the job forever. The second check inside the lock is needed: a worker that waited for the lock must see the marker written by the one that held it.

The same guard serves the stock events, where the identity is the `event_id` created by the Go service.

## The cart on three instances

Two questions come up.

**Does the first instance see a change made through the second?** Yes. The cart is a value in Redis, keyed by user (`cart:user:{id}`). No instance keeps a copy. `app1` writes it, `app3` reads it from Redis, and there is nothing to invalidate. The collection [Requirement-5-v5](../ECBackend/postman/Requirement-5-v5.postman_collection.json) and `loadtest/cart.js` check this: they add through one request, change through another, and read, and the request goes through nginx so each can be served by a different instance.

**What if two requests change the same cart at the same moment?** Each change reads the cart, modifies it and writes it back. Two instances doing that at once can both read the same old cart, and the second write erases the first change (a lost update). Every cart operation therefore runs inside `Cache::lock('cart:lock:{userId}')`, waiting up to `CART_LOCK_WAIT_SECONDS` for it. The lock is per user, so different users never wait for each other. If the lock cannot be obtained in time, the API answers `409 Conflict` with "The cart is being updated. Try again." instead of losing the change silently.

## Problems met and how each was solved

| # | What happened | Why | How it was fixed |
| --- | --- | --- | --- |
| 1 | After a Redis restart every cart disappeared and mails were sent a second time | Redis kept data in memory only, so the carts and the done-markers vanished with the container | `redis-server --appendonly yes --appendfsync everysec` and a `redis_data` volume. At most about one second of writes can be lost after a crash |
| 2 | Under memory pressure Redis could delete a lock or a done-marker, and the work would run twice | The default eviction policy removes keys to make room | `--maxmemory 512mb --maxmemory-policy noeviction`. When memory is full, writes fail loudly instead of removing someone else's key |
| 3 | A mail could be sent twice when two workers took the same job | De-duplication was check-then-act, two steps with a gap | An atomic lock and a second check inside it, `OnceGuard`, see above |
| 4 | A stock event redelivered by RabbitMQ could be broadcast twice | Same race, on the consumer side | The same `OnceGuard`, keyed by `event_id` |
| 5 | With three instances starting together, migrations and seeding ran three times in parallel and failed on duplicate tables or rows | Every `app` container ran `migrate` and `db:seed` at start | A one-shot `migrate` service runs them once. The instances start after it completed (`service_completed_successfully`). The entrypoint of the `app` role no longer migrates |
| 6 | Two quick changes to the cart through different instances could leave only one of them | Read, modify, write without exclusion | A lock per user around every cart operation, `409` when it cannot be obtained |
| 7 | One instance handled a single request at a time, so the three instances still queued requests | `php artisan serve` is single threaded unless told otherwise | `PHP_CLI_SERVER_WORKERS` (`PHP_WORKERS`, default 4) |
| 8 | Links and redirects produced by an instance pointed at its own address | `APP_URL` and the `Host` header were those of the internal container | nginx forwards `Host $http_host`, and the web client and `APP_URL` point at nginx on 8000 |
| 9 | After `docker compose restart app2` nginx kept sending requests to the old address and returned 502 | nginx resolves upstream names once, at start | `resolve` in the `upstream` block with the Docker resolver, plus a shared memory `zone` |
| 10 | A failed `POST /orders` could be repeated on another instance and create two orders | nginx retries failed requests on the next server | `proxy_next_upstream` lists only connection errors, timeouts and 502, 503, 504. nginx does not retry a non-idempotent request once it has been sent, unless `non_idempotent` is added, and it is not |
| 11 | Nothing showed which instance had answered, so distribution could not be proven | No instance identity in the response | Middleware `ServedBy` adds `X-Served-By`, exposed to browsers through CORS. k6 counts it |
| 12 | Logs stayed inside three containers and were hard to read together | Logs were written to files in each container | `LOG_CHANNEL=stderr`, read with `docker compose logs -f app1 app2 app3` |
| 13 | A token issued by one instance could be rejected by another | Each instance could have generated its own `APP_KEY` or `JWT_SECRET` | One `x-backend` environment anchor gives every instance and worker the same secrets, and the compose file refuses to start without them |

## Failure behaviour

| Event | Result |
| --- | --- |
| An instance stops | nginx fails the request on that instance and repeats it on another (a read or a request that never reached the instance). After three failures the instance is skipped for 10 seconds. The other two serve all requests |
| The stopped instance starts again | nginx resolves it again within 5 seconds and returns it to the rotation. It waits for its health check in Compose |
| A worker stops | The other two keep reading the queue. A message that was being processed and not acknowledged returns to the queue. The done-marker prevents a duplicate effect |
| Two workers receive the same message | One takes the lock and sends. The other sees `BUSY` or `DUPLICATE` |
| Redis restarts | AOF restores the data. During the restart requests that need Redis fail |

A request that was already being processed by the instance when it stopped can fail with a 5xx, because nginx cannot know whether it was applied. That is the reason a `POST` is not repeated. The failover test therefore allows a small error rate (`FAIL_RATE`, 5 percent in `failover.ps1`) instead of zero.

## Known limits

| Limit | Consequence | What would remove it |
| --- | --- | --- |
| nginx, Redis, the two MySQL servers, RabbitMQ, the Go service and Reverb are each a single instance | The loss of any of them stops the part that depends on it. Requirement 5 scales the Laravel layer, which is the one that was asked for | Redis Sentinel or Cluster, MySQL replication, a RabbitMQ cluster, a second nginx with a virtual address |
| One Reverb server | The WebSocket layer does not scale horizontally as configured | `REVERB_SCALING_ENABLED=true` with Redis publish and subscribe, and a load balancer for WebSocket traffic |
| `product_images` is a Docker named volume | The three instances share it only on one Docker host | Object storage, for example an S3 compatible disk |
| `php artisan serve` is a development server | Enough for the demonstration. It is not the production server | PHP-FPM with nginx, or Octane |
| One consumer | One reader of `ec.stock.changed` keeps event order simple | More consumers are safe because of the lock, at the price of looser ordering |
| Redis restart and a second Docker host were not exercised | The stack ran on one host and the distribution, failover, cart and mail checks passed, but persistence across a Redis restart is configured and unproven | Run the steps in [load-testing.md](load-testing.md) and see [verification-status.md](verification-status.md) |
