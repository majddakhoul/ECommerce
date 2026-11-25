# Troubleshooting

> Part of the [ECommerce](../../README.md) repository, requirement 4 ([FReq_04](../README.md)).

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

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md) · [Requirement 5](../../FReq_05/README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

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
| Cart is empty after a restart in Docker | Redis was reset with `down -v` | Expected. The cart lives in the cache |
| Changed `.env` has no effect | Laravel cached the configuration | `php artisan config:clear` |
| `docker compose up` stops with `copy .env.example to .env first` | A required variable is empty | Fill in `APP_KEY`, `JWT_SECRET`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` |
| The web container works but shows the wrong API address | Build arguments are baked into the image | Rebuild with `docker compose build web` after changing them |
