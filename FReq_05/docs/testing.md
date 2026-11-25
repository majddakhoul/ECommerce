# Testing

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

## Laravel

From `ECBackend`:

```powershell
php artisan test
```

The suite has 187 tests. The requirement 4 tests are in `tests/Feature/V4`, the requirement 5 tests in `tests/Feature/V5`.

| Test class | What it proves |
| --- | --- |
| `ProcessingModeTest` | The mode follows the path and appears in `X-Processing-Mode`. Both modes expose the authentication endpoints. Earlier versions carry no mode header. The bare `/api/v4` prefix does not exist |
| `OrderProcessingTest` | Async queues the mail job and publishes the event, sync sends the mail inline and publishes nothing. A job that runs twice delivers once. Cancellation restocks and publishes. Order events target the owner and the admin channels |
| `RealtimeEventsTest` | The product lifecycle, the cart and checkout publish in async mode and nothing in sync mode. The gateway asks the inventory service to publish only in async mode |
| `StockEventHandlerTest` | A valid event is broadcast once, a new event id is broadcast again, malformed events are rejected, and the event uses the public `stock` channel |
| `BroadcastAuthTest` | The owner may join their order and cart channels, another user may not, an administrator may join `orders.admin` but not a customer cart, a missing token is refused, and a deactivated user is refused |

Requirement 5 adds 12 tests in `tests/Feature/V5`:

| Test class | What it proves |
| --- | --- |
| `OnceGuardTest` | The work runs once. A second call is `DUPLICATE`. A call made while another holds the lock is `BUSY` and does not run, and runs after the lock is released. A failed run leaves no marker and can be retried |
| `ConcurrentDeliveryTest` | A mail job and a stock event arriving while another worker holds the lock are neither sent nor broadcast, and are sent or broadcast once after it is released |
| `CartConcurrencyTest` | A cart change while the user's lock is held answers `409`. The cart read is the same in sync and async mode |
| `InstanceHeaderTest` | `X-Served-By` is on successful and on rejected responses, follows `INSTANCE_NAME`, and is exposed to browsers by CORS |

The array cache of the test environment implements the same lock interface as Redis, so these tests prove the logic of the guard and of the cart lock. They cannot prove that two real processes compete correctly. That is what `loadtest/cart.js`, `loadtest/mail.js` and the failover run are for, see [load-testing.md](load-testing.md).

`tests/Support/PusherLikeBroadcaster.php` is a test double that mimics the Pusher signature, because the Reverb and Pusher packages could not be installed in the environment where the tests were written. Run the suite after `composer install` to confirm nothing depends on the double.

## Go

From `ECInventory`:

```powershell
go test -race ./internal/...
```

| Package | What it proves |
| --- | --- |
| `internal/events` | Event construction, the JSON shape, the context flag |
| `internal/events/rabbitmq` | Publisher buffering, confirms, reconnect, drop on a full buffer, drain on close, tested with a fake broker |
| `internal/service` | `SetStock`, `DeleteStock`, `Reserve` and `Release` publish exactly once after the commit, and publish nothing when the context flag is absent |
| `internal/config` | The new `RABBITMQ_*` and `EVENT_BUFFER` variables |

The real AMQP adapter in `internal/events/rabbitmq/broker` is only compiled in the Docker build. It is not covered by a unit test. Runbooks 4 to 6 in [reliability.md](reliability.md) exercise it.

The integration tests of requirement 2 are unchanged:

```powershell
go test -tags integration -count=1 ./tests/integration/...
```

## React

From `ECWeb`:

```powershell
npm test
```

```powershell
npm run typecheck
```

| Test file | What it proves |
| --- | --- |
| `rules.test.ts` | Order status transitions, stock levels, event ordering rule, money formatting, role checks |
| `realtime.test.ts` | Stock updates in lists and carts, shortage detection, product upsert and removal, duplicate suppression |
| `dashboard.test.ts` | Revenue excludes cancelled orders, status counts, seven-day buckets, top products, benchmark statistics |
| `config.test.ts` | Mode selection, base path per mode, realtime disabled in sync mode, query parameter cleaning |
| `mappers.test.ts` | Wire format to domain model conversion |

## Load measurement

[`loadtest/orders.js`](../loadtest/README.md) places orders against one mode. Run it once per mode and compare the two result files. The scripts `scale.js`, `cart.js`, `mail.js` and `failover` of requirement 5 are described in [load-testing.md](load-testing.md). The web client also has an admin page, Lab, that alternates between the two modes from the browser.

## Manual checks

| Check | Expected |
| --- | --- |
| `docker compose ps` | Three `app`, three `worker`, one `nginx`, `migrate` exited with code 0 |
| Twelve requests to `/api/v4/async/products` through `http://localhost:8000` | `X-Served-By` shows `app1`, `app2` and `app3` |
| `docker compose stop app2` and repeat | Only `app1` and `app3` answer, no error |
| RabbitMQ management, queue `emails` | Three consumers |
| Place an order with `--profile mail` | One mail per recipient in Mailpit, never two |

The checks of the web client:

| Check | Expected |
| --- | --- |
| Two browsers, one admin and one user. The user places an order in async mode | The admin order list shows the order within a second and the dashboard counters change |
| The admin changes the status of that order | The user sees the status badge change without reloading |
| The admin changes a product stock | Product cards of both browsers show the new quantity and flash |
| A cart holds 5 units, the admin sets the stock to 2 | The cart shows the shortage message and the checkout button is disabled |
| Switch the client to sync on the profile page | The pill reads "Live updates off", no WebSocket is opened, and everything else still works |
