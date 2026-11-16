# ECommerce

A Laravel 12 REST API with a Flutter client, built in three requirements for the web applications course at Damascus University. Each requirement lives in its own folder and builds on the previous one.

[Requirement 1](FReq_01/README.md) · [Requirements 2 and 3](FReq_02_03/README.md) · [Documentation index](FReq_02_03/docs/README.md) · [Verification status](FReq_02_03/docs/verification-status.md)

| Folder | Requirement | Contents | Guide |
| --- | --- | --- | --- |
| [`FReq_01`](FReq_01) | 1. API and security | Laravel API (`/api/v1`), JWT, roles, rate limiting, Flutter client | [FReq_01/README.md](FReq_01/README.md) |
| [`FReq_02_03`](FReq_02_03) | 2. Communication between services, 3. Performance and caching | Laravel API (`/api/v2`, `/api/v3`), Go inventory service, gRPC contract, Redis cache, ETag, read replica, indexes, k6 measurements, Docker platform | [FReq_02_03/README.md](FReq_02_03/README.md) |

## How the requirements connect

```
FReq_01      Laravel API + Flutter                        /api/v1   stock inside Laravel
   |
FReq_02_03   + Go inventory service over gRPC             /api/v2   stock moved out, reservations
             + cache, replica, indexes, measurements      /api/v3   same contract, faster reads
```

- The three API versions run side by side in the code of FReq_02_03, so one request can be compared across versions with the same data.
- The Flutter client of FReq_01 works against every version, because v2 and v3 keep the v1 contract.
- FReq_02_03 runs on its own, with Docker or without it.

## Where to read next

| Question | Document |
| --- | --- |
| How is the whole system designed? | [FReq_02_03/docs/architecture.md](FReq_02_03/docs/architecture.md) |
| How do I run it with Docker? | [FReq_02_03/docs/docker.md](FReq_02_03/docs/docker.md) |
| How do I run it without Docker? | [FReq_02_03/docs/local-setup.md](FReq_02_03/docs/local-setup.md) |
| How do I run the tests? | [FReq_02_03/docs/testing.md](FReq_02_03/docs/testing.md) |
| How does the Go service guarantee that stock is never oversold? | [FReq_02_03/ECInventory/README.md](FReq_02_03/ECInventory/README.md) |
| What does the gRPC contract contain? | [FReq_02_03/proto/README.md](FReq_02_03/proto/README.md) |
| How does the v3 read path work? | [FReq_02_03/ECBackend/README.md](FReq_02_03/ECBackend/README.md#performance-and-caching-v3) |
| What were the measured results? | [FReq_02_03/PERFORMANCE.md](FReq_02_03/PERFORMANCE.md#results) |
| What was verified and what was not? | [FReq_02_03/docs/verification-status.md](FReq_02_03/docs/verification-status.md) |
| Something failed | [FReq_02_03/docs/troubleshooting.md](FReq_02_03/docs/troubleshooting.md) |

## Quick start

With Docker Desktop, in PowerShell:

```powershell
cd FReq_02_03
```

```powershell
Copy-Item .env.example .env
```

```powershell
docker compose up --build
```

The API answers on `http://localhost:8000`. The Docker configuration has not been executed yet, see its [status](FReq_02_03/docs/docker.md). The set-up that was run and verified is described in [local-setup.md](FReq_02_03/docs/local-setup.md).

## Rules for this repository

- The `main` branch is protected. Work happens on a branch and enters `main` through a merge request.
- Never commit a `.env` file. Only `.env.example` files with development values are committed.
- Generated code (`gen/`, `vendor/`) is not committed.
- Code contains no comments. Intent is expressed through names and structure, and explanations live in the documentation.

## License

MIT, see [LICENSE](LICENSE).
