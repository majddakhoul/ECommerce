# Documentation index

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

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../../FReq_02_03/README.md) · [Requirement 4](../README.md)

[Documentation index](README.md) · [Architecture](architecture.md) · [Realtime](realtime.md) · [Reliability](reliability.md) · [Configuration](configuration.md) · [Docker](docker.md) · [Local set-up](local-setup.md) · [Testing](testing.md) · [Security](security.md) · [Compliance](compliance.md) · [Troubleshooting](troubleshooting.md) · [Verification status](verification-status.md)

This folder holds the cross-cutting documentation of requirement 4. Each component also has its own README next to its code.

## Start here

| Goal | Document |
| --- | --- |
| Understand how the pieces fit together and why | [architecture.md](architecture.md) |
| Look up channels, events and payloads | [realtime.md](realtime.md) |
| Look up any environment variable | [configuration.md](configuration.md) |
| Check each requirement against its implementation and evidence | [compliance.md](compliance.md) |
| See how retries, dead letters and worker failure are handled, and how to prove it | [reliability.md](reliability.md) |
| Run the platform with Docker | [docker.md](docker.md) |
| Run the platform without Docker | [local-setup.md](local-setup.md) |
| Run every test and understand what it proves | [testing.md](testing.md) |
| Review the security design | [security.md](security.md) |
| Fix a problem that came up during set-up | [troubleshooting.md](troubleshooting.md) |
| See exactly what was run and what was not | [verification-status.md](verification-status.md) |

## Component documents

| Component | Document |
| --- | --- |
| Laravel API, sync and async paths, events, commands | [ECBackend/README.md](../ECBackend/README.md) |
| Go inventory service and the `stock.changed` publisher | [ECInventory/README.md](../ECInventory/README.md) |
| React web client | [ECWeb/README.md](../ECWeb/README.md) |
| gRPC contract and event contract | [proto/README.md](../proto/README.md) |
| k6 sync versus async measurement | [loadtest/README.md](../loadtest/README.md) |
| Postman collection | [ECBackend/README.md](../ECBackend/README.md#postman-collection) |

## Reading order for a reviewer

1. [architecture.md](architecture.md) for the design and the reasons behind it.
2. [reliability.md](reliability.md) for the failure handling and the runbook that proves it.
3. [verification-status.md](verification-status.md) for the evidence and its limits.

## Conventions

- Commands are written for PowerShell on Windows unless the block is marked `bash`.
- Every command block can be copied and run as it is, from the folder named in the sentence above it.
- Code blocks contain no comments. Explanations are in the text around them.
