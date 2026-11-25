# Ideas and design

This page explains what we built in each requirement and why we made the choices we did. It is the page to read before the others if you want the big picture.

[Repository](../README.md) · [Documentation](README.md) · [Environment setup](environment-setup.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Problems and solutions](problems-and-solutions.md) · [Troubleshooting](troubleshooting.md)

Every requirement builds on the one before it. The old API routes (`/api/v1` to `/api/v3`) keep working exactly as they did, and new behavior arrives on new paths. That way a later step never breaks an earlier one.

## The finished system

```text
React client (5173)      Flutter app
        |                      |
        +----------+-----------+
                   |
             nginx (8000)            requirement 5
        least_conn, three instances
        +----------+-----------+
        |          |           |
      app1       app2        app3      Laravel API, stateless
        |          |           |
        +----------+-----------+
          |        |       |      |
        MySQL    Redis  RabbitMQ  gRPC
                   |       |      |
                 cart,   workers  Go inventory
                 locks   (x3)     service, MySQL
                           |
                       Reverb (WebSocket) -> browsers
```

## Requirement 1: a clean API

The first step is a normal shop API: register, log in, browse products, keep a cart, place orders, and an admin side for managing it all.

We split every request into layers. A form request validates it and checks permissions, a controller receives it, a service holds the business rules, a repository talks to the database. It is more files than putting everything in the controller, but each layer can be tested on its own and the rules live in one place.

Login uses JWT, so the server keeps no session. That choice looks like a small detail at this stage, but it is what later allows several servers to answer the same user. There are two roles, `admin` and `user`. Five wrong passwords lock an account for 15 minutes, and an admin can unlock it earlier.

The most important rule is about stock. When an order is placed the stock goes down, and when it is cancelled the stock comes back. The decrease is a single SQL statement that checks and subtracts at the same time, so two people buying the last item at the same moment cannot both succeed. Every response, good or bad, uses the same JSON shape so the clients only need one parser.

The Flutter app uses every endpoint, which is how we checked that the API is actually usable.

Details: [Backend README](../FReq_01/ECBackend/README.md), [Flutter README](../FReq_01/ECFrontend/README.md).

## Requirement 2: the inventory becomes its own service

In a bigger system the stock is not owned by the shop API. So we moved it into a separate Go service with its own database, and Laravel talks to it over gRPC. The two databases share nothing except the product id.

The only thing both sides share is one contract file, `inventory.proto`. Both sides generate their code from it, so they cannot slowly drift apart.

An order with five items makes one `ReserveStock` call, not five. The service reserves all five inside one transaction, or none. Each call carries a `reservation_id` made by the client, and if the same id arrives twice the service just returns the earlier answer. That protects against a client that retries after a timeout. Releasing a reservation puts the quantities back once, and only once.

The two kinds of failure look different on purpose. "Not enough stock" is a `409`, because the customer asked for something we cannot give. "The inventory service is down or too slow" is a `503`, because something is broken. A client can handle those very differently.

Details: [architecture](../FReq_02_03/docs/architecture.md), [Inventory README](../FReq_02_03/ECInventory/README.md), [contract](../FReq_02_03/proto/README.md).

## Requirement 3: faster, and proven faster

We did not want to guess at performance, so every optimization has a switch and a way to measure it alone.

Product reads are cached, and the cache is cleared on every write. We tested that with and without clearing: with it, no read was stale, and without it 49 of 50 reads of a changed product were stale. Stock is never cached, because a stale stock number is a wrong answer. It is always read live from the inventory service.

We added `ETag` and `304 Not Modified` so a client that already has a page does not download it again. A FULLTEXT index replaced a table scan for search, and it turned out to be the only index that changed a query plan. The others were already covered. Eager loading cut the orders list from 31 queries to 3. There is also an optional read replica, with a short window after each write where reads go to the primary so nobody sees old data right after saving.

One result surprised us. On our test machine the cache did not lower the tail latency, because the fixed cost of each request (starting the framework, checking the JWT, asking for live stock) is bigger than the database time that was left. We report that as it is. The numbers and their limits are in [results.md](results.md).

Details: [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md).

## Requirement 4: do slow work in the background, push changes live

Sending an email inside the request makes the customer wait for the mail server. In this step the same controllers serve two paths. On `/api/v4/sync` the email goes out inside the request, and on `/api/v4/async` it becomes a job on a RabbitMQ queue. Because only the side effects differ, the two modes can be compared fairly.

Jobs retry with growing delays (10, 60, 300 and 900 seconds) and end in a failed queue if they never succeed, so a broken mail server loses nothing. RabbitMQ can deliver a message twice, so a mail carries a key and is sent only once, and stock events are deduplicated by their `event_id`.

The Go service now announces `stock.changed` after each committed change. Because the service that owns the number is the one that announces it, even changes that do not come through Laravel are noticed. A malformed event is rejected into a dead letter queue so it cannot block the others.

Laravel Reverb pushes updates to browsers over WebSockets. Catalog and stock channels are public. Orders and carts use private channels that are authorized against the user's JWT, so nobody sees someone else's order. The React client is organised into `Domain`, `Data`, `Presentation` and `Services` folders to keep the business logic free of framework code.

Details: [architecture](../FReq_04/docs/architecture.md), [realtime](../FReq_04/docs/realtime.md), [reliability](../FReq_04/docs/reliability.md).

## Requirement 5: more than one server

The last step runs three copies of the API behind nginx. The idea only works if no copy keeps anything important in its own memory, so the cart, locks and de-duplication markers all live in one Redis. Any copy can then answer any request, and you can add or stop a copy freely.

nginx uses `least_conn`, which sends each new request to the copy with the fewest open connections. Requests are not all the same length, so counting connections balances better than taking turns.

Three workers read the same queues. That gives more throughput, but it also means two workers could pick up related jobs at the same time. To prevent a double email we wrote `OnceGuard`: it checks a done marker, takes a lock, and checks the marker again inside the lock. A plain "check, then act" has a gap between the two steps, and the second check closes it.

The cart has a similar problem. Two quick changes that land on different copies could overwrite each other, so every cart change takes a lock for that user. If the lock is busy the API answers `409` and the client can retry.

A few smaller decisions matter too. Every response carries an `X-Served-By` header so you can see which copy answered, which is how we proved the distribution. nginx does not retry a `POST` that has already been sent, so a failed order is never repeated on another copy and turned into two orders. A one-shot `migrate` service runs the migrations once instead of three copies racing. All copies share the same `APP_KEY` and `JWT_SECRET`, so a token from one is valid on the others.

Details: [scaling](../FReq_05/docs/scaling.md), [architecture](../FReq_05/docs/architecture.md), [load testing](../FReq_05/docs/load-testing.md).

## What this design does not solve

We want to be clear about the limits.

- nginx, Redis, the databases, RabbitMQ, the Go service and Reverb are each a single instance. Three API copies do not make the whole system highly available. See [scaling.md](../FReq_05/docs/scaling.md#known-limits).
- The product images are stored on a volume that is shared only on one Docker host.
- `php artisan serve` is a development server. A production setup would use PHP-FPM or Octane.
- All three copies run on one computer and share one processor, so we make no claim of higher throughput. The test proves how work is spread and what happens when a copy fails, not raw speed.
- The cache version is global, and on our test machine the cache did not lower the tail latency. See [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md#known-limits).

The problems we hit while building all this are told in [problems-and-solutions.md](problems-and-solutions.md).
