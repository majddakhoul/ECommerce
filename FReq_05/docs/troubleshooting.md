# Troubleshooting

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

| Symptom | Cause | Fix |
| --- | --- | --- |
| `composer install` reports a lock file mismatch | `composer.json` was changed without updating the lock file | Run `composer update` once and commit the new `composer.lock` |
| `composer` fails with `ext-sockets is missing` | `php-amqplib` needs the sockets extension, which is commented out in the default XAMPP `php.ini` | Remove the semicolon in front of `extension=sockets`, then open a new terminal |
| `Class "PhpAmqpLib\\Connection\\AMQPStreamConnection" not found` | Dependencies not installed after the update | `composer install` in `ECBackend` |
| `Unable to create a socket` or `Call to undefined function socket_create` | The PHP `sockets` extension is off | Enable `extension=sockets` in `php.ini` and restart |
| `Connection refused` on port 5672 | RabbitMQ is not running | Start the container or the Windows service, then check `http://localhost:15672` |
| `PRECONDITION_FAILED - inequivalent arg` | A queue exists with different arguments, for example one created by an older run | Delete the queue in the management interface, or reset the broker, then run `php artisan messaging:topology` |
| Jobs stay in `Ready` and nothing is processed | No worker is running, or it listens to other queues | Start `php artisan queue:work rabbitmq --queue=emails,broadcasts` |
| The web pill says "Live updates off" | The client is in sync mode, or `VITE_REVERB_APP_KEY` is empty | Switch to async on the profile page. Set the key and restart `npm run dev` |
| The pill says "Reconnecting" for a long time | Reverb is not running, or the host or port in `.env` is wrong | Start `php artisan reverb:start`. Check `VITE_REVERB_HOST` and `VITE_REVERB_PORT` |
| Private channels fail with 401 or 403 | The token expired, the account is locked, or the user does not own the channel | Sign in again. Check the Network tab for the `/api/broadcasting/auth` response |
| Browser blocks the request with a CORS error | The origin is not allowed | Add it to `CORS_ALLOWED_ORIGINS` and run `php artisan config:clear` |
| The pill stays on "Reconnecting" and `reverb:start --debug` prints `Origin not allowed` | `REVERB_ALLOWED_ORIGINS` holds full origins | Use host names only, for example `REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1`, then `php artisan config:clear` and restart Reverb |
| Stock changes in the admin page but other browsers do not update | The Go service has no `RABBITMQ_HOST`, the consumer is not running, or the client is in sync mode | Set `RABBITMQ_HOST`, start `php artisan inventory:consume`, switch to async |
| No events after an order | The order was placed on the sync path | Place it on `/api/v4/async` |
| The API answers 503 on stock calls | The Go service is not running or the API key differs | Start it. Compare `INVENTORY_API_KEY` on both sides |
| Cart is empty after a restart in Docker | Redis was reset with `down -v` | Expected. The cart lives in the cache. A plain restart keeps it, Redis writes an append-only file |
| `nginx: [emerg] unknown directive` or `resolve` is refused | An image older than 1.27.3, or a build without the upstream zone | Use `nginx:1.30.2-alpine` as in the compose file and keep the `zone` line in the upstream |
| nginx answers 502 on every request | The instances are not healthy yet, or `migrate` failed | `docker compose ps`, then `docker compose logs migrate app1` |
| Requests always reach the same instance | A single client with one keep-alive connection and `least_conn` on an idle system | Send concurrent requests. `scale.js` does |
| The cart answers `409 The cart is being updated` | Two changes for the same user at once, or a lock left by a crashed instance | Retry. A lock expires after `CART_LOCK_SECONDS` |
| `make : The term 'make' is not recognized` on Windows | `make` is not installed | Run the `protoc` command from the Makefile by hand, see [local-setup.md](local-setup.md#3-inventory-service) |
| `InventoryUnavailableException` during `php artisan migrate --seed` | `ProductSeeder` calls the Go inventory service over gRPC and it is not running | Start the service first. `Test-NetConnection 127.0.0.1 -Port 50051` must report `TcpTestSucceeded : True` |
| The first `docker compose up --build` runs for 15 to 40 minutes, and the log ends with `[output clipped, log limit 2MiB reached]` | The `grpc` PHP extension is compiled from source. The message only means Docker stopped printing the log | Wait. The layer is cached afterwards. Do not use `--no-cache` or `docker system prune -a` |
| The web pill says "Live updates off" although Reverb runs | The client is in sync mode, `VITE_REVERB_APP_KEY` is empty, or Vite was not restarted after editing `.env` | Switch to async on the profile page, set the key, restart `npm run dev` and reload with Ctrl+F5 |
| `.\loadtest\failover.ps1 cannot be loaded because running scripts is disabled` | The PowerShell execution policy | `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass`, then run the script again |
| The first `k6` run downloads layers for a while | The `grafana/k6` image is pulled once | Wait |
| `migrate` prints a `Connection refused` stack trace to RabbitMQ and then `RabbitMQ topology declared.` | RabbitMQ answered its health check slightly before it accepted connections. The entrypoint retries every 2 seconds | Nothing to fix |
| `mail.js` reports `No mail reached Mailpit` | `MAIL_MAILER` is `log` | Set `MAIL_MAILER=smtp` in `.env` and start with `--profile mail` |
| RabbitMQ logs `no queue 'emails' in vhost '/'` again and again | The workers poll the queues before the first job creates them. The package recovers by itself, so nothing is lost | Fixed by declaring `emails` and `broadcasts` in `messaging:topology`, which the `migrate` container runs. Rebuild with `docker compose up --build` |
| `X-Served-By` is missing | The request did not reach Laravel, for example `/nginx-health` | Request an `/api` path |
| A mail is sent twice | Instances use different cache stores | Make every instance and worker use the same Redis, see [scaling.md](scaling.md) |
| `migrate` exits with an error and the instances never start | Database not ready, or a migration failed | Read `docker compose logs migrate`. Fix it, then `docker compose up -d` |
| Local nginx does not start, or Reverb does not | Both use port 8080 | Make nginx listen on 8000, see [local-setup.md](local-setup.md#several-instances-without-docker) |
| Images do not appear on another instance when not using Docker | Each local copy has its own `storage` folder | Run the copies from one folder, or use shared storage |
| Changed `.env` has no effect | Laravel cached the configuration | `php artisan config:clear` |
| `docker compose up` stops with `copy .env.example to .env first` | A required variable is empty | Fill in `APP_KEY`, `JWT_SECRET`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` |
| The web container works but shows the wrong API address | Build arguments are baked into the image | Rebuild with `docker compose build web` after changing them |
