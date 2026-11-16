# Performance measurement guide

> Part of [FReq_02_03](README.md), requirement 3. Technical description of every mechanism: [backend README](ECBackend/README.md#performance-and-caching-v3). The inventory service and its contract come from requirement 2, described in the [guide](README.md#requirement-2---communication-between-services).

The numbers in this file must come from your own machine. The tables at the end are empty on purpose. Copy the values from the JSON files in `loadtest/results` and from the `compare` responses.

## Preparation

```powershell
cd FReq_02_03
Copy-Item .env.example .env
docker compose up --build -d
docker compose exec app php artisan perf:seed --products=50000 --orders=200000
```

Log in as `admin@example.com` (password `password`) and keep the token. Every lab call needs it.

## Running without Docker

The same steps work on a local PHP and MySQL setup. Differences: there is no Redis (`PERFORMANCE_STORE=file`), so cache numbers are lower than with Redis, and there is no replica, so step 3 is skipped.

1. Install k6 with `winget install k6 --source winget` and reopen the terminal.
2. Use a separate database, for example `ecommerce_perf`, in `.env` of `ECBackend`, and set `API_RATE_LIMIT=100000`.
3. Run `php artisan migrate --seed`, then `php artisan perf:seed --products=50000 --orders=200000 --skip-inventory`.
4. In `ECInventory` set `APP_DB_NAME=ecommerce_perf` and run `go run ./cmd/seed`, which writes one quantity for every product.
5. Start the application with `PHP_CLI_SERVER_WORKERS=4` so that requests are served in parallel.
6. Run every k6 command as `k6 run -e BASE_URL=http://localhost:8000 -e RESULTS_DIR=loadtest/results -e VERSION=... -e LABEL=... loadtest/compare.js`.

## Variables that must stay the same between runs

Same machine, no other heavy program running, same data set, same `VUS` and `DURATION`. Run each k6 test twice and keep the second run, so that MySQL buffers are warm.

## Step 0 - Baseline

Remove the indexes added for this requirement and switch every optimization off:

```powershell
docker compose exec app php artisan perf:indexes drop
```

`PUT /api/v3/performance/flags` with `{"cache": false, "etag": false, "replica": false, "fulltext": false}` (Postman request `02. Set flags (baseline)`).

In-process comparison, repeated for the five scenarios `list`, `search`, `category`, `detail` and `orders` (Postman folder `Requirement 3 - Performance lab`):

```
GET /api/v3/performance/compare?scenario=search&term=phone&runs=30
```

Query plans:

```
GET /api/v3/performance/explain?scenario=search_like&term=phone
GET /api/v3/performance/explain?scenario=category&category=electronics
GET /api/v3/performance/explain?scenario=orders_by_user&user_id=1
```

Load test against the unmodified v2 routes:

```powershell
docker compose --profile tools run --rm -e VERSION=v2 -e LABEL=step0-v2-baseline -e VUS=20 -e DURATION=60s k6 run /scripts/compare.js
```

## Step 1 - Indexes and FULLTEXT

```powershell
docker compose exec app php artisan perf:indexes add
```

Switch `fulltext` on and the others off, then repeat the `explain` calls (`search_fulltext` now uses the FULLTEXT index, `category` and `orders_by_user` use the composite indexes) and the k6 run:

```powershell
docker compose --profile tools run --rm -e VERSION=v3 -e LABEL=step1-indexes -e VUS=20 -e DURATION=60s k6 run /scripts/compare.js
```

## Step 2 - Cache and ETag

Switch `cache` and `etag` on (`03. Set flags (optimized)`), flush the cache, run the same k6 command with `LABEL=step2-cache-etag`. The `compare` endpoint now shows the cache hit ratio and the saved queries. The k6 summary shows the number of cache hits and `304` responses.

## Step 3 - Read replica

Check that replication works:

```
GET /api/v3/performance/status
```

`replica.io_running` and `replica.sql_running` must be `Yes`. Switch `replica` on, flush the cache and run k6 with `LABEL=step3-replica`. Under the product list requests, `X-Data-Source` shows `mysql_replica` on a cache miss.

## Step 4 - N+1

`GET /api/v3/performance/compare?scenario=orders&runs=30` compares lazy loading with eager loading. The expected queries per run for 15 orders are 31 against 3.

## Step 5 - Immediate update proof

```powershell
docker compose --profile tools run --rm k6 run /scripts/consistency.js
```

The script changes a product and reads it back through the cached v3 routes 200 times. The thresholds `stale_detail_reads`, `stale_list_reads` and `stale_stock_reads` must all be 0.

To see the failure that the protection prevents, switch `invalidation` off, flush the cache, and run:

```powershell
docker compose --profile tools run --rm -e EXPECT_STALE=true k6 run /scripts/consistency.js
```

The detail reads are now stale until the TTL ends. Switch `invalidation` back on afterwards (or `DELETE /api/v3/performance/flags`).

## What to expect and why

| Change | Effect |
| --- | --- |
| Composite index and FULLTEXT | The search stops scanning every row, `rows` in `explain` drops from the table size to a few rows, and the sort on `id` is served by the index |
| Cache | A hit needs no SQL query and no `COUNT(*)`. Only one `GetStocks` gRPC call remains, because the stock is live |
| ETag | The server still builds the response, but a `304` has no body, so bytes and client work drop |
| Replica | Reads move off the primary, which matters when writes and reads compete. The latency of a single read does not change |
| Eager loading | Queries per request drop from 1 + 2N to 3 |

The cache cannot make a cold request faster, and a replica cannot make a single query faster. State this in the conclusion of the report, with the numbers.

## Results

Measured on one Windows machine with XAMPP MySQL, the PHP built-in server (a single process), the file cache (no Redis), 50,000 products and 200,000 orders, 5 virtual users for 60 seconds. Every figure comes from a single run, so differences of about 10 to 20 percent are within the noise.

### Load test (k6, mixed read traffic)

| Step | Label | RPS | p50 ms | p95 ms | p99 ms | Cache hits | 304 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 0 | step0-v2-baseline | 2.99 | 1545 | 2392 | 2604 | 0 | 0 |
| 1 | step1-indexes | 3.39 | 1460 | 1569 | 1704 | 0 | 0 |
| 2 | step2-cache-etag | 3.32 | 1386 | 2001 | 2133 | 122 | 52 |
| 3 | step3-replica | not run, needs Docker | | | | | |

### In-process comparison (`compare`, 30 runs, optimizations on)

| Scenario | Before p50 | After p50 | Before queries | After queries | Speed-up |
| --- | --- | --- | --- | --- | --- |
| list | 16.8 ms | 2.6 ms | 2 | 0 | 6.5x |
| search | 120.5 ms | 2.7 ms | 2 | 0 | 43.9x |
| category | 5.6 ms | 2.7 ms | 2 | 0 | 2.1x |
| detail | 1.8 ms | 1.9 ms | 1 | 0 | 0.95x |
| orders (N+1) | 17.1 ms | 3.2 ms | 31 | 3 | 5.4x |

### Query plans (`explain`)

| Scenario | Index used before | Rows before | Index used after | Rows after |
| --- | --- | --- | --- | --- |
| search | PRIMARY (LIKE, no usable index) | 15 | ft_products_name_description (fulltext) | 1 |
| category | products_category_index | 2488 | products_category_index | 2488 |
| orders by user | orders_user_id_foreign | 1 | idx_orders_user_id_id | 1 |

### Consistency (`consistency.js`, 50 rounds)

| Run | Stale detail reads | Stale list reads | Stale stock reads |
| --- | --- | --- | --- |
| Invalidation on | 0 | 0 | 0 |
| Invalidation off | 49 | 49 | 0 |

## Findings

1. The FULLTEXT index is the only new index that changes a query plan. `products_category_index` and the foreign key index already cover the category and the orders-by-user queries, so the two composite indexes added by `perf:indexes` do not change those plans. The `rows` value of the LIKE search is an estimate that stops at the `LIMIT`, the measured time of 120 ms shows the real cost.
2. Step 1 lowered p95 by 34 percent and p99 by 35 percent against the baseline, which is consistent with the search requests moving from LIKE to FULLTEXT. Step 0 ran on the v2 routes and step 1 on the v3 routes, so the gain is not caused by the index alone, and step 0 ran once on a cold buffer pool.
3. The cache removed every database query on a hit (2 to 0) and the in-process comparison shows the largest effect on search and lists. In the load test it did not lower the tail latency compared with step 1. The fixed cost of each request on this setup (framework start-up, JWT, the live stock call to the inventory service, the file cache and the log files) is larger than the database time that is left after the indexes. A cache on Redis and a multi-process server would be needed to see the effect on latency.
4. A detail request by primary key is already fast, so the cache gives no gain there.
5. The ETag turned 52 requests into empty `304` responses, which saves the body on the network and not the server work.
6. With invalidation off, 49 of 50 reads of a modified product were stale. With invalidation on, none were stale. The stock is never stale because it is read from the inventory service on every request, cached or not.
7. The read replica was not measured because it needs the Docker environment.

## Known limits

- The cache version is global, so a write to any product makes every cached page a miss once. For a catalog that changes rarely this is cheap and simple. A write-heavy catalog would need keys per product and per page range.
- The replica is protected by a time window. If the replica lags by more than `PERF_REPLICA_STICKY_SECONDS`, a read can be older than a write. `status` reports the lag, and the window is configurable.
- The cache is not protected against a stampede. When a key expires under heavy load, several requests can rebuild it at the same time.
- `perf:seed` writes the stock rows directly into the inventory database, which is acceptable for a test data set and must not be used in production.
