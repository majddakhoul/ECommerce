# Delivery

> Part of the [ECommerce](../README.md) repository. Back to the [documentation index](README.md).

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md) · [Delivery](delivery.md)

How to publish the work, what to check before and what to hand in. The repository is `https://github.com/majddakhoul/ECommerce.git`. Work is published on the branch `checkout` and merged into `master` through a pull request, so that `master` always holds a finished requirement.

## Before publishing

1. Rotate every secret that was ever shared outside your machine: the Gmail app password (delete the old one in the Google account and create a new one), `APP_KEY` and `JWT_SECRET`. See [environment-setup.md](environment-setup.md#secrets).
2. Make sure no `.env` file is staged. The root `.gitignore` ignores `.env` and `.env.*` and keeps `.env.example`. Check it:

```powershell
git status --short
```

```powershell
git ls-files | Select-String "(^|/)\.env$"
```

The second command must print nothing.

3. Run the checks of the requirement that changed:

```powershell
php artisan test
```

from `FReq_05/ECBackend`, and the stack with `docker compose up --build` from `FReq_05`, see [requirement-5.md](requirement-5.md).

## Publish with the script

The script `publish-requirement-5.ps1` makes one small commit per concern, on the branch `checkout`, and pushes it. It stops without committing anything when a `.env` file would be included.

1. Clone the repository once, or open your existing clone:

```powershell
git clone https://github.com/majddakhoul/ECommerce.git
```

2. Copy the project files over the clone: the folder `FReq_05`, the folder `docs`, the file `.gitattributes`, and the files that changed in the other folders. Do not copy `.env` files. A zip that is built for delivery contains none.

3. Put `publish-requirement-5.ps1` in the repository root, next to the folder `.git`, and from the root run:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

```powershell
.\publish-requirement-5.ps1
```

4. Open the repository on GitHub, choose the branch `checkout`, and create a pull request into `master`.

Run the script from the repository root. It refuses to run elsewhere. If it stops with an error, read the line that names the git command that failed, fix it, and run the script again: commits that already exist are not repeated, because a step with nothing new to add is skipped.

### Without the script

The same commits, one command at a time, are in `commit-sequence-5.md`, which is delivered together with the script. Run each block from the repository root.

## Commit messages

One line, a type, an optional scope, then what the commit does in the present tense.

| Type | Use |
| --- | --- |
| `feat` | New behaviour |
| `fix` | A defect fixed |
| `test` | Tests, k6 scripts and Postman collections |
| `build` | Docker, compose and nginx |
| `docs` | Documentation |
| `chore` | Everything else |

Examples: `feat(backend): add the atomic once guard for shared work`, `build(compose): run three instances, three workers and nginx`, `docs: add the repository documentation hub`.

## Pull request

Title: `Requirement 5: scalability and load distribution`. The body states what was added, how to run it, and the evidence:

| Section | Content |
| --- | --- |
| What | Three instances and three workers behind nginx `least_conn`, one shared Redis, the atomic guard, the cart lock and the `X-Served-By` header |
| Run | `docker compose up --build` from `FReq_05`, then the k6 commands of [requirement-5.md](requirement-5.md#prove-the-distribution) |
| Evidence | The table of [results.md](results.md#requirement-5): 167 / 167 / 167, failover, 0 cart mismatches, 102 mails with 0 duplicates |
| Not shown | The list in [results.md](results.md#not-demonstrated) |

## What to hand in

| Item | Where |
| --- | --- |
| The repository link and the pull request | GitHub |
| The documentation entry point | [docs/README.md](README.md) and [FReq_05/docs/README.md](../FReq_05/docs/README.md) |
| The Postman collections | [FReq_05/ECBackend/postman](../FReq_05/ECBackend/postman) |
| The k6 scripts and the result files | [FReq_05/loadtest](../FReq_05/loadtest/README.md) |
| Screenshots | Below |

## Screenshots

Take these from the running stack. They are the visible proof of the claims in [results.md](results.md).

| Screenshot | How to get it |
| --- | --- |
| The stack healthy | `docker compose ps` |
| Distribution of 500 requests | The table printed by `scale.js`, with 167 / 167 / 167 |
| Failover | The table printed by `failover.ps1`, with `app2` lower |
| Cart consistency | The end of `cart.js`, with `cart_mismatch` 0 |
| Three consumers | RabbitMQ at `http://localhost:15672`, tab Queues, queue `emails` |
| Mail audit | The line `messages: 102, distinct: 102, duplicated: 0` |
| The mails | Mailpit at `http://localhost:8025` |
| The web client | `http://localhost:5173` signed in as the administrator, with the pill "Live" in async mode |
| Tests | `php artisan test` ending with 187 tests passed |

## After the merge

```powershell
git checkout master
```

```powershell
git pull origin master
```

The branch `checkout` can be kept for the next requirement.
