# Problems and solutions

Nothing worked on the first try, and a lot of what we learned came from fixing things. This page tells those stories: what went wrong, why, and what we did about it. If something is broken for you right now, [troubleshooting.md](troubleshooting.md) is organised by symptom and is faster to search.

[Repository](../README.md) · [Documentation](README.md) · [Ideas and design](ideas-and-design.md) · [Environment setup](environment-setup.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

## Problems the design had to solve

### Overselling (requirements 1 and 2)

The obvious way to take stock is to read it, check that it is enough, then write the new value. Between the read and the write another request can sneak in, and then two customers get the last item. We replaced the three steps with one statement, `stock = stock - ? WHERE stock >= ?`, so the check and the subtraction cannot be separated.

An order with several items had a related problem: it could reserve the first two items and then fail on the third. The inventory service now takes the whole order in one call and one transaction, and rolls everything back if any line fails.

Retries were the next trap. If a client times out and sends the order again, the stock would be taken twice. Each call carries a `reservation_id` chosen by the client, and a repeated id returns the first answer instead of doing the work again. If Laravel reserves stock and then fails to save the order, it releases the reservation, and a release only counts once.

Finally, the client needs to tell "you asked for too much" apart from "the service is down". Those are `409` and `503`.

### Stale data and a cache that did not help (requirement 3)

With the cache on and no invalidation, 49 of 50 reads of an edited product were stale. Clearing the cache on every write brought that to zero. Stock is never cached, since a stale stock figure is simply wrong.

Two results were less flattering and we reported them anyway. The cache did not lower p95 and p99 latency on our machine, because the fixed cost of each request is bigger than the database time it saves. And the first measurement step looked like a big win from the new index, but that step ran on a cold database against the `v2` routes while the next step ran on `v3`, so the gain cannot be credited to the index alone. [results.md](results.md#what-these-numbers-do-and-do-not-show) spells out the limits.

A read replica can return old data right after a write because of replication lag. After each write, reads go to the primary for a short window.

### Queues that fail in quiet ways (requirement 4)

If an email is sent inside the request, a slow mail server makes the order slow, and a broken one makes it fail. Moving the mail to a queue fixes that, and it brings its own problems.

A poisoned stock event would be retried forever and block the queue behind it. Now it is rejected without requeue and lands in a dead letter queue. RabbitMQ can deliver the same message twice, so each mail has a key, and each stock event has an `event_id` and a version, so a duplicate or an older event is ignored.

On the WebSocket side, public channels are open to everyone, so orders and carts use private channels checked against the JWT. Events are sent only after the database commit, otherwise a rolled-back order would still announce itself. In the Go service the broker publish is buffered so a stock change never waits for RabbitMQ.

### Sharing work across servers (requirement 5)

| Problem | What we did |
| --- | --- |
| Cart and done markers vanish if Redis restarts | Persistence with an append-only file and a volume |
| Redis could evict a lock under memory pressure | `noeviction`, so a write fails loudly instead of silently deleting a key |
| Two workers take related jobs and send the same email twice | `OnceGuard`: done marker, lock, second check inside the lock |
| Three instances run migrations and seeding at the same time | One `migrate` service that the instances wait for |
| Two cart changes on different instances overwrite each other | A lock per user, `409` when busy |
| A single instance handles only one request at a time | `PHP_CLI_SERVER_WORKERS` |
| nginx keeps an old address after a container restarts | `resolve` and a `zone` in the `upstream` block |
| A failed `POST /orders` retried on another instance creates two orders | nginx never retries a request that was already sent |
| Nothing shows which instance answered | The `X-Served-By` header |
| A token from one instance is refused by another | The same `APP_KEY` and `JWT_SECRET` everywhere |

The longer write-up of these is in [scaling.md](../FReq_05/docs/scaling.md#problems-met-and-how-each-was-solved).

## Problems we hit while setting up and testing

These all happened on a Windows machine the first time we ran the project. They are the kind of thing you are likely to run into too.

**`make` is not recognized.** `make proto` fails on Windows because there is no `make`. We ran the `protoc` commands from the Makefile by hand. They are listed in [requirement-2-3.md](requirement-2-3.md#inventory-service).

**`protoc` cannot find its Go plugins.** The folder `%USERPROFILE%\go\bin` was not on the `PATH`. We installed the two generators with `go install` and added the folder to the `PATH`.

**Seeding stops with `InventoryUnavailableException`.** The product seeder calls the Go service over gRPC, and it was not running. `Test-NetConnection 127.0.0.1 -Port 50051` printed `False`. Start the Go service first, then seed.

**The first Docker build seems endless.** `docker compose up --build` ran for a very long time and ended with `[output clipped, log limit 2MiB reached]`. The PHP `grpc` extension is compiled from source, which takes a long time, and the message only means the log was cut. The layer is cached afterwards, so later builds take seconds.

**RabbitMQ logs `no queue 'emails'` over and over.** The queue library creates a queue only when the first job is published, and the workers started polling before that. The `messaging:topology` command now declares both queues, with their dead letter settings, before anything else starts.

**`migrate` prints `Connection refused` to RabbitMQ.** RabbitMQ passes its health check slightly before it accepts connections. It is harmless, because the entrypoint retries every two seconds.

**PowerShell refuses to run `failover.ps1`.** The execution policy blocks scripts. `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass` fixes it for the current window only.

**`docker compose ps` says `no configuration file provided`.** You are in the wrong folder. Go to the folder of the stack, for example `FReq_05`.

**The k6 read test gets `401`.** The first version of `scale.js` sent no token. It now logs in during setup for both workloads.

**Two PHPUnit tests failed.** The mistakes were in the tests, not the product. One needed an authenticated request and one captured a variable by value. All 187 tests pass now.

**Mailpit stays empty.** `MAIL_MAILER` was `log`, so mail went to the log file and never reached the SMTP server. Set `MAIL_MAILER=smtp`, start the `mail` profile, and recreate the containers, because they only read their environment when created. With that, the audit shows 102 messages and 0 duplicates.

**A mail audit that passed over nothing.** The first run reported `duplicated: 0` over zero messages, which proves nothing. `mail.js` now fails when no message arrives.

**`docker compose down` warns that a network is still in use.** Mailpit and k6 belong to profiles, and a plain `down` does not stop them. Use `docker compose --profile mail --profile tools down`. The warning itself is harmless.

**The RabbitMQ page shows version 4.4.0 and no consumers.** A RabbitMQ installed on Windows was answering on port 15672 instead of the container, which runs 3.13. Stop the Windows service, or move the container to other ports with `RABBITMQ_PORT` and `RABBITMQ_UI_PORT`.

**Reverb says `Origin not allowed`.** `REVERB_ALLOWED_ORIGINS` held a scheme and a port. It wants host names only, for example `localhost,127.0.0.1`.

**The profile page says "Live updates off".** The client is in sync mode, which opens no WebSocket by design. Switch to async on the profile page, and check that `VITE_REVERB_APP_KEY` is set.

## Problems we found while cleaning up

**A login token inside the k6 result files.** k6 keeps the setup data in its summary, and the scripts wrote the whole summary to disk. The scripts now remove `setup_data` before writing, and the stored result files were cleaned.

**Real `.env` files next to the code.** A `.env` holds real secrets and must never be shared. The root `.gitignore` excludes `.env` and `.env.*` and keeps `.env.example`. If a secret ever leaves your machine, replace it.

**Shell scripts that break after a Windows clone.** Git can turn line endings into CRLF, and a script with CRLF does not run inside a Linux container. A root `.gitattributes` pins the line endings of scripts.

**Leftover Laravel comments.** Nineteen framework files still had default comments. We removed them with the PHP tokenizer and checked that the code tokens were identical before and after.

**Documentation that was out of date.** Several pages still said Docker had never been run. They were rewritten with the real results, and each claim now names its evidence.

## What is still open

These were not demonstrated, and the documentation says so instead of implying otherwise. See [results.md](results.md#not-demonstrated).

- That the data survives a Redis restart.
- A run on a second Docker host.
- Live updates between two browsers, and the reliability runbooks.
- The Postman collections of requirements 4 and 5, which have been generated and validated as JSON but not run in Postman.
