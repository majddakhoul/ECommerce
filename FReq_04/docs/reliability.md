# Reliability

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

This page describes what happens when something fails, and gives a runbook to prove each claim with the RabbitMQ management interface at `http://localhost:15672` (user and password `guest` by default). The automated tests cover the logic. The runbook is the proof against a real broker, and it has to be executed by the person who runs the stack, see [verification-status.md](verification-status.md).

## Guarantees

The table states the designed behaviour. Unit and feature tests cover the application logic (retry counts, idempotency, validation, de-duplication, ordering). The rows that depend on the broker itself are proven only by the runbooks below.

| Failure | Behaviour |
| --- | --- |
| Queue worker stops | Jobs stay in the queue. They are processed when a worker starts again |
| Worker dies while processing a job | The message was not acknowledged, so RabbitMQ returns it to the queue |
| A job throws | Retried with back-off. After the last try it is rerouted to `<queue>.failed` |
| Job redelivered after a completed delivery | Skipped by the idempotency key. A crash between delivery and the key write can still duplicate a mail |
| Stock consumer crashes | Unacknowledged events return to `ec.stock.changed` |
| Stock event is malformed | Rejected without requeue, so it lands in `ec.stock.changed.dlq` at once |
| Stock event keeps failing | Republished with an incremented `x-attempts` header, then dead-lettered after three attempts |
| Same stock event delivered twice | Dropped by `event_id` |
| Events arrive out of order | The client applies an event only if its `version` is not older than the last one for that product |
| RabbitMQ is down while the API runs | Expected: dispatching a job raises an exception, so the request fails instead of silently losing the mail |
| RabbitMQ is down while Go changes stock | The publisher reconnects with back-off. Events beyond the buffer are dropped. Clients refetch on reconnect |
| Reverb is down | The API request still succeeds. Clients reconnect automatically and refetch their data |

## Runbook 1: a stopped worker loses nothing

1. Start the stack and open the queues page of the management interface.
2. Stop the queue worker (Ctrl+C in its terminal, or `docker compose stop worker`).
3. Place three orders through the async path (the web client in async mode, or the Postman request "02. Place order").
4. The queue `emails` now shows three messages in `Ready`, and the API answered every request normally.
5. Start the worker again (`docker compose start worker`).
6. The `Ready` count drops to zero. Three mails appear in `storage/logs/laravel.log` (mailer `log`) or in Mailpit.

## Runbook 2: a killed worker returns the message

1. Set `MAIL_MAILER=smtp` with an unreachable host, so each job takes the connection timeout.
2. Place an order. The message moves to `Unacked`.
3. Kill the worker process while the message is `Unacked`.
4. The message returns to `Ready` within seconds, because the channel closed without an acknowledgement.

## Runbook 3: retries and the failed queue

1. Keep the unreachable SMTP host.
2. Place an order and watch the worker output. The job is retried after 10, 60, 300 and 900 seconds.
3. To avoid waiting, lower `backoff` in `app/Jobs/SendOrderMail.php` temporarily, or set `--tries=1` on the worker.
4. After the last try the message appears in `emails.failed` in the management interface.

## Runbook 4: a poisoned stock event reaches the dead letter queue

1. Open the exchange `ec.events` in the management interface.
2. Publish a message with the routing key `stock.changed` and the body `not json`.
3. The consumer rejects it. The queue `ec.stock.changed.dlq` now holds one message.
4. Publish `{"event_id":"x","type":"stock.changed","product_id":1,"stock":-5}`. A negative stock fails validation and also ends in the dead letter queue.
5. Move or purge the dead letters from the management interface after inspecting them.

## Runbook 5: duplicates are ignored

1. Publish the same valid event twice with the same `event_id` through the management interface.
2. Open the web client on the product list. The stock changes once. The consumer log shows the second message as a duplicate.

## Runbook 6: an unavailable broker

1. Stop RabbitMQ: `docker compose stop rabbitmq`.
2. Change a product stock through the async API. The Laravel call succeeds. The Go log reports reconnect attempts.
3. Start RabbitMQ again. The publisher reconnects. Events published after that point flow normally. Events produced during the outage within the buffer are delivered after the reconnect.

## Operations

| Task | Command |
| --- | --- |
| Declare every exchange and queue | `php artisan messaging:topology` |
| Process jobs | `php artisan queue:work rabbitmq --queue=emails,broadcasts --tries=3` |
| Consume stock events | `php artisan inventory:consume` |
| Run the consumer for a fixed number of messages | `php artisan inventory:consume --max-messages=10` |
| Inspect the queues | `http://localhost:15672` |
