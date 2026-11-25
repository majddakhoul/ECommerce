# Results

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

This page gathers every test result and measurement of the five requirements, says where each came from and what it does not prove. The per-requirement files with the full evidence are linked in each section.

## How to read the results

| Word | Meaning |
| --- | --- |
| Measured | A number taken from the output or the result file of a run that was executed |
| Passed | An automated test or a threshold that passed in a run that was executed |
| Observed | Something seen on the machine, reported but not automated |
| Not run | Written and reviewed, never executed. No claim is made about it |

Every measurement is a single run on one Windows machine, not an average. Differences of 10 to 20 percent are within the noise. The load tests ran with Docker Desktop on that machine, so the instances, the databases and k6 shared one processor. They show distribution and resilience, not throughput that a real deployment would reach.

## Summary

| Requirement | Automated tests | Load or behaviour evidence | Docker |
| --- | --- | --- | --- |
| 1 | 107 test methods in the Laravel suite | Postman flows | Not recorded |
| 2 | 148 Laravel tests, Go unit and integration tests | Race: 1 order created, 9 conflicts, 0 unexpected | Not run |
| 3 | The same suite | Index, cache and consistency measurements | Not run, replica step not run |
| 4 | 175 Laravel tests, Go tests, 27 React tests | Order and mail flow through the web client on a development machine | Not run |
| 5 | 187 Laravel tests, 669 assertions | Distribution 167 / 167 / 167, failover, cart, sync against async, 102 mails with 0 duplicates | Run, all services healthy |

## Requirement 1

Source: [FReq_01](../FReq_01/README.md), described in [requirement-1.md](requirement-1.md).

| Item | Result |
| --- | --- |
| Laravel suite (`php artisan test`) | 107 test methods in seven classes. The count of passing tests of this snapshot was not recorded |
| Postman collection | 41 requests |
| Flutter client | Unit tests for validators, model parsing and `422` parsing. Not retested against `/api/v2` and later |

## Requirements 2 and 3

Source: [FReq_02_03/PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md) and [verification-status.md](../FReq_02_03/docs/verification-status.md). Environment: one Windows machine, XAMPP MySQL, the PHP built-in server (one process), the file cache, 50,000 products, 200,000 orders, 5 virtual users for 60 seconds.

### Requirement 2

| Item | Result |
| --- | --- |
| Laravel suite | 148 tests passed |
| Go unit tests, service, seed and config | Passed |
| Go integration tests on real MySQL | Three tests passed: ten concurrent reservations for stock 1 gave one success, a release restores once, eight identical concurrent reservations decrement once |
| End to end race, `race.js` | One order created, nine conflicts, none unexpected |
| Order above the stock | `409` |
| Inventory service stopped | `503` |

### Requirement 3, load test (`compare.js`)

| Step | Label | Requests per second | p50 ms | p95 ms | p99 ms | Cache hits | `304` |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 0 | step0-v2-baseline | 2.99 | 1545 | 2392 | 2604 | 0 | 0 |
| 1 | step1-indexes | 3.39 | 1460 | 1569 | 1704 | 0 | 0 |
| 2 | step2-cache-etag | 3.32 | 1386 | 2001 | 2133 | 122 | 52 |
| 3 | step3-replica | Not run | | | | | |

### Requirement 3, in-process comparison (30 runs, optimizations on)

| Scenario | p50 before | p50 after | Queries before | Queries after | Speed-up |
| --- | --- | --- | --- | --- | --- |
| list | 16.8 ms | 2.6 ms | 2 | 0 | 6.5x |
| search | 120.5 ms | 2.7 ms | 2 | 0 | 43.9x |
| category | 5.6 ms | 2.7 ms | 2 | 0 | 2.1x |
| detail | 1.8 ms | 1.9 ms | 1 | 0 | 0.95x |
| orders (N+1) | 17.1 ms | 3.2 ms | 31 | 3 | 5.4x |

### Requirement 3, consistency (`consistency.js`, 50 rounds)

| Run | Stale detail reads | Stale list reads | Stale stock reads |
| --- | --- | --- | --- |
| Invalidation on | 0 | 0 | 0 |
| Invalidation off | 49 | 49 | 0 |

### What these numbers do and do not show

- Step 1 lowered p95 by 34 percent and p99 by 35 percent. Step 0 ran on the `v2` routes on a cold buffer pool and step 1 on the `v3` routes, so the gain is not caused by the index alone.
- The cache removed every database query on a hit but did not lower the tail latency, because the fixed cost of each request (framework start-up, JWT, the live stock call, the file cache) is larger than what is left of the database time.
- The absolute throughput of about 3 requests per second is a property of the single-process server on Windows.
- The read replica and the Redis cache were not measured, see [Not demonstrated](#not-demonstrated).

The reasoning behind each finding is in [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md#findings).

## Requirement 4

Source: [verification-status.md](../FReq_04/docs/verification-status.md).

| Item | Result |
| --- | --- |
| Laravel suite | 175 tests passed |
| Go tests | Passed with `go test -race` on events, the RabbitMQ publisher, the service and the config |
| React | Type check passed in strict mode, 27 unit tests passed, production build passed |
| Pages render without console errors | Checked in a headless browser against a mocked API |
| Laravel, Go service and the order flow on a development machine | Observed working through the web client, outside Docker |
| Reverb browser connection | Refused at first with `Origin not allowed`. Fixed by using host names only in `REVERB_ALLOWED_ORIGINS`, see [realtime.md](../FReq_04/docs/realtime.md#allowed-origins) |

The queue and Reverb behaviour was then exercised inside the requirement 5 stack, see the next section.

## Requirement 5

Source: the k6 result files in `FReq_05/loadtest/results` and the terminal output of the runs, summarized in [load-testing.md](../FReq_05/docs/load-testing.md#results-observed) and [verification-status.md](../FReq_05/docs/verification-status.md). Environment: Docker Desktop on Windows, three instances, three workers, `MODE=async`, k6 0.54.0 inside the stack.

### Stack

| Item | Result |
| --- | --- |
| `docker compose up --build` | All services healthy. `migrate` exited with code 0 before the instances started |
| nginx `1.30.2-alpine` | Pulled, running and healthy |
| Laravel suite | 187 tests, 669 assertions passed (PHP 8.3.6) |
| RabbitMQ queues `emails` and `broadcasts` | Declared by the topology at start-up. Before that fix the workers logged `no queue 'emails'` and `no queue 'broadcasts'` while polling, because the queue package creates a queue only when it first publishes. After the fix the log was clean |

### Distribution

| Run | Requests | `app1` | `app2` | `app3` | No header | Failed | p95 | Result |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 500 reads over 2 minutes | 501 | 167 (33.3%) | 167 (33.3%) | 167 (33.3%) | 0 | 0 of 502 | 55.2 ms | Passed |
| 50 orders over 30 seconds | 51 | 17 (33.3%) | 17 (33.3%) | 17 (33.3%) | 0 | 0 of 53 | 240.9 ms | Passed, 51 of 51 orders created |

For the read run the average was 37.3 ms, the median 30.7 ms, p99 85.7 ms and the maximum 701.6 ms. For the order run the average was 96.8 ms, the median 64.3 ms, p99 622.0 ms and the maximum 780.3 ms.

### Failover, `app2` stopped at second 60 and started at second 100

| Instance | Requests | Share |
| --- | --- | --- |
| `app1` | 197 | 39.4% |
| `app2` | 104 | 20.8% |
| `app3` | 198 | 39.6% |
| No instance header | 1 | 0.2% |

500 requests, 1 failed request out of 501, average 39.2 ms, p95 61.0 ms, p99 87.4 ms and a maximum of 2002.1 ms. The thresholds passed. `app2` served 104 requests instead of about 167 because it was out of the rotation for about 40 seconds, and `app1` and `app3` absorbed the difference. The single request without a header and the 2 second maximum are consistent with a request that was in progress on `app2` when it stopped. No other cause was investigated.

### Cart consistency, `cart.js`

| Item | Result |
| --- | --- |
| Iterations | 50, with 553 HTTP requests |
| Reads of the cart | 400, split `app1` 134, `app2` 132, `app3` 134 |
| `cart_mismatch` | 0 |
| Checks | 450 passed, 0 failed |
| Failed requests | 0 of 553, p95 69.0 ms |

### Sync against async, `orders.js`

| Mode | Orders created | Per second | Median | p95 | p99 | Failed requests |
| --- | --- | --- | --- | --- | --- | --- |
| `sync` | 910 | 19.9 | 490 ms | 604 ms | 640 ms | 0 of 912 |
| `async` | 1473 | 32.3 | 303 ms | 402 ms | 450 ms | 0 of 1475 |

Ten virtual users for about 45 seconds each, through nginx, one run per mode. The async path was 38 percent faster at the median and handled about 62 percent more orders per second. The mail driver of these runs was not recorded. With the `log` driver the mail is almost free, so most of the gap comes from the stock events and broadcasts that async moves out of the request. The result files are `sync.json` and `async.json` in `FReq_05/loadtest/results`.

### Mail audit, `mail.js` with Mailpit

| Item | Result |
| --- | --- |
| Orders placed | 51, through all three instances (17 each) |
| Messages received by Mailpit | 102, two per order: one to the customer, one to the administrator |
| Distinct recipient and subject pairs | 102 |
| Duplicated | 0 |
| Driver | `MAIL_MAILER=smtp`, host `mailpit`, port 1025, confirmed with `printenv` in `app1` |

An earlier run used the `log` driver, so Mailpit received nothing (`messages: 0`) and the `duplicated: 0` of that run proved nothing. `mail.js` now fails when no message arrives, and the result above comes from the run with SMTP.

### Corrections made while testing

| Problem found | Fix |
| --- | --- |
| Two failing PHPUnit tests | Test errors, not product errors. One needed an authenticated request, the other captured a variable by value |
| `scale.js` read workload returned `401` | The script now logs in during setup for both workloads |
| Postman read requests were sent without a token | Inherited bearer authentication |
| The documentation said `MIN_SHARE` was a ratio | It is a percentage and only reported |
| Workers polled queues that did not exist | The topology declares `emails` and `broadcasts` with their dead letter arguments |

## Not demonstrated

| Item | Why it matters |
| --- | --- |
| Stacks of requirements 2 to 3 and 4 in Docker | The documents of those requirements record no Docker run. The images of requirement 5 contain the same code and did build and run |
| Redis cache numbers and the read replica step of requirement 3 | The measurement machine had no Redis and no replica |
| Runbooks 1 to 6 of [reliability.md](../FReq_04/docs/reliability.md) | The behaviours are implemented and tested at code level, but were not shown on a running broker |
| Live updates between two browsers | Not confirmed in any recorded run. The sync mode shows "Live updates off" by design |
| Redis restart keeps the cart, locks and markers | Not run |
| Several Docker hosts | Not run. The image volume works on one host only |
| Delivery to a real mailbox | The audit used Mailpit. No mail went to Gmail |
| Postman collections of requirements 4 and 5 | Generated by script and valid JSON, never run in Postman |
| Flutter client against `/api/v2` and later | Not retested |
| Throughput gain from three instances | Not claimed, because everything shared one machine |

## Reproduce a result

| Result | How | Page |
| --- | --- | --- |
| Test counts | `php artisan test` in the folder of the requirement | [commands.md](commands.md#tests) |
| Race proof | `loadtest/race.js` | [requirement-2-3.md](requirement-2-3.md#test) |
| Performance steps | [PERFORMANCE.md](../FReq_02_03/PERFORMANCE.md) | [requirement-2-3.md](requirement-2-3.md#performance-measurement) |
| Distribution, failover, cart | `scale.js`, `failover.ps1`, `cart.js` | [requirement-5.md](requirement-5.md#prove-the-distribution) |
| Mail audit | The six steps | [requirement-5.md](requirement-5.md#no-mail-is-delivered-twice) |
