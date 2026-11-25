# Troubleshooting

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

Problems met while setting up and running the project, with the cause and the fix. Each requirement also has a longer table next to its code: [requirements 2 and 3](../FReq_02_03/docs/troubleshooting.md), [requirement 4](../FReq_04/docs/troubleshooting.md) and [requirement 5](../FReq_05/docs/troubleshooting.md). The requirement 5 table is the most complete and covers everything the earlier ones do.

## Before anything else

1. Run every command from the folder named on its page. Most "file not found" errors come from the wrong folder.
2. Read the first error, not the last. A long stack trace is usually one failure repeated.
3. After editing a `.env`, restart what reads it. Docker containers read their environment when they are created, so `docker compose up -d` after the edit is not enough for a value that changed in a running container; use `docker compose down` then `up`. Laravel run locally needs `php artisan config:clear`.

## Windows and PowerShell

| Symptom | Cause | Fix |
| --- | --- | --- |
| `make : The term 'make' is not recognized` | Windows has no `make` | Run the `protoc` commands by hand, see [requirement-2-3.md](requirement-2-3.md#inventory-service) |
| `protoc-gen-go: program not found or is not executable` | `%USERPROFILE%\go\bin` is not on the `PATH` | Add it, open a new PowerShell, and run `go install` for the two generators again |
| `cannot be loaded because running scripts is disabled on this system` | PowerShell execution policy | `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass`, then run the script again. The setting lasts for the current window only |
| `no configuration file provided: not found` | `docker compose` was run outside the stack folder | `cd` to the folder that holds `docker-compose.yml`, for example `D:\ECommerce\FReq_05` |
| A script prints `bad interpreter` or `\r: command not found` | A shell script was checked out with Windows line endings | The repository sets `eol=lf` for scripts in [`.gitattributes`](../.gitattributes). Re-clone, or run `git add --renormalize .` and check the files out again |
| `composer` fails with `ext-sockets is missing` | The sockets extension is commented out in `php.ini` | Remove the semicolon in front of `extension=sockets`, open a new terminal |
| `could not find driver` in the tests | `pdo_sqlite` or `pdo_mysql` is off | Enable the extension in `php.ini` |

## Docker

| Symptom | Cause | Fix |
| --- | --- | --- |
| The first build runs for 15 to 40 minutes | The PHP `grpc` extension is compiled from source | Wait. The layer is cached afterwards. Do not use `--no-cache` or `docker system prune -a` |
| The log ends with `[output clipped, log limit 2MiB reached]` | Docker stopped printing the build log | Nothing is wrong. Wait for the command to finish |
| `port is already allocated` | Another stack or program uses the host port | Stop the other stack with `docker compose down` in its folder, or change the port in `.env`, see [the port table](environment-setup.md#ports-used-by-each-stack) |
| `Conflict. The container name "/ec5_rabbitmq" is already in use` | A container of a local set-up has the same name | `docker rm -f ec5_rabbitmq` |
| `docker compose down` prints `Network freq_05_default Resource is still in use` | Mailpit or a k6 container of a profile is still attached | Harmless. Run `docker compose --profile mail --profile tools down` to remove everything |
| `docker compose ps` does not list `migrate` | It exited with code 0 after finishing | `docker compose ps -a` shows it. This is the expected state |
| `migrate` prints a `Connection refused` trace to RabbitMQ, then `RabbitMQ topology declared.` | RabbitMQ answered its health check just before accepting connections. The entrypoint retries every 2 seconds | Nothing to fix |
| `up` stops with `copy .env.example to .env first` | A required variable is empty | Fill `APP_KEY`, `JWT_SECRET`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` |
| The web container shows the wrong API address | The address is baked into the image at build time | `docker compose build web` after changing it |
| A change in the backend code has no effect | The image was not rebuilt | `docker compose up --build -d` |
| Cart or data is gone after a restart | `down -v` removed the volumes | Expected. A plain `down` and `up` keeps them |

## Databases and the inventory service

| Symptom | Cause | Fix |
| --- | --- | --- |
| `InventoryUnavailableException` during `php artisan migrate --seed` | The product seeder calls the Go service over gRPC and it is not running | Start the service first. `Test-NetConnection 127.0.0.1 -Port 50051` must report `TcpTestSucceeded : True` |
| Orders answer `503` | The Go service is stopped, or `INVENTORY_API_KEY` differs between the two sides | Start it. Compare the key on both sides |
| Product stock is wrong or missing after `migrate:fresh` | Product ids changed, so the inventory no longer matches | Run `go run ./cmd/seed` in `ECInventory` with `APP_DB_NAME` set |
| The Go integration test seems to hang | It retries the database connection for about 30 seconds without `DB_USER`, `DB_PASSWORD` and `DB_NAME` | Set the three variables first |

## RabbitMQ, workers and mail

| Symptom | Cause | Fix |
| --- | --- | --- |
| Jobs stay `Ready` and nothing is processed | No worker runs, or it listens to other queues | Start `php artisan queue:work rabbitmq --queue=emails,broadcasts --tries=3`, or check the worker containers |
| RabbitMQ logs `no queue 'emails' in vhost '/'` repeatedly | Workers polled queues that nobody had declared yet | Fixed in requirement 5: `messaging:topology` declares `emails` and `broadcasts`. Rebuild with `docker compose up --build` |
| `PRECONDITION_FAILED - inequivalent arg` | A queue exists with different arguments from an older run | Delete the queue in the management interface, then run `php artisan messaging:topology` |
| Mailpit is empty although orders were placed | `MAIL_MAILER` is `log`, so mail goes to `storage/logs/laravel.log` | Set `MAIL_MAILER=smtp` in the root `.env`, `docker compose --profile mail --profile tools down`, then `docker compose --profile mail up -d`, and check with `docker compose exec app1 printenv MAIL_MAILER` |
| `mail.js` reports `No mail reached Mailpit` | The same cause | The same fix. The script fails on purpose when no message arrived |
| Mail audit counts mails of earlier runs | Mailpit keeps its messages while it runs | `Invoke-RestMethod -Method Delete http://localhost:8025/api/v1/messages` before the run |

## Web client and Reverb

| Symptom | Cause | Fix |
| --- | --- | --- |
| The pill says "Live updates off" | The client is in sync mode, which by design opens no WebSocket. Or `VITE_REVERB_APP_KEY` is empty | Switch to async on the profile page. Set the key and restart `npm run dev`, or rebuild the `web` image |
| `reverb:start --debug` prints `Origin not allowed` | `REVERB_ALLOWED_ORIGINS` holds full origins | Use host names only, for example `localhost,127.0.0.1`, then `php artisan config:clear` and restart Reverb |
| The pill stays on "Reconnecting" | Reverb is not running, or the host or port is wrong | Start it and check `VITE_REVERB_HOST` and `VITE_REVERB_PORT` |
| A CORS error in the browser | The origin is not allowed | Add it to `CORS_ALLOWED_ORIGINS`, then `php artisan config:clear` |
| Stock changes in the admin page but other browsers do not update | The consumer is not running, or the Go service has no `RABBITMQ_HOST`, or the client is in sync mode | Start `php artisan inventory:consume`, set `RABBITMQ_HOST`, switch to async |

## Load tests

| Symptom | Cause | Fix |
| --- | --- | --- |
| `scale.js` reports `401` | An older copy of the script sent no token | Use the current `loadtest/scale.js`, which logs in during setup |
| The distribution table shows `none` | The response did not come from Laravel, for example nginx answered `502` while no instance was healthy | `docker compose ps`, then read `docker compose logs app1` |
| A few `none` during failover | Requests in progress on the stopped instance | Expected for a few requests. The threshold is 5 percent |
| Requests always reach one instance | One client with a single connection on an idle system | Send concurrent requests. `scale.js` does |
| `cart.js` reports `409 The cart is being updated` | Two changes for the same user at once, or a lock left by a crashed instance | Retry. The lock expires after `CART_LOCK_SECONDS` |
| The first k6 run downloads for a while | The `grafana/k6` image is pulled once | Wait |

## Secrets

| Symptom | Cause | Fix |
| --- | --- | --- |
| A secret was shared or committed | It is now known to others | Replace it: a new mail app password (and delete the old one), a new `APP_KEY`, a new `JWT_SECRET`, see [environment-setup.md](environment-setup.md#secrets). If it reached Git history, rewrite or discard that history, because deleting the file in a later commit does not remove it |
| The publish script stops with `A .env file would be committed` | A `.env` is not ignored | Fix `.gitignore` and remove the file from the index with `git rm --cached` |
