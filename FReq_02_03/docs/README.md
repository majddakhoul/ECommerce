# Documentation index

> Part of the [ECommerce](../../README.md) repository, requirements 2 and 3 ([FReq_02_03](../README.md)).

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter&logoColor=white" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL 8">
<img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/JWT-auth-000000?logo=JSON%20web%20tokens" alt="JWT">
<img src="https://img.shields.io/badge/Go-1.24-00ADD8?logo=go&logoColor=white" alt="Go 1.24">
<img src="https://img.shields.io/badge/gRPC-inventory.v1-244C5A?logo=grpc&logoColor=white" alt="gRPC">
<img src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white" alt="Redis 7">
<img src="https://img.shields.io/badge/k6-load%20tests-7D64FF?logo=k6&logoColor=white" alt="k6">
<img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

[Repository](../../README.md) · [Requirement 1](../../FReq_01/README.md) · [Requirements 2 and 3](../README.md)

This folder holds the cross-cutting documentation. Each component also has its own README next to its code.

## Start here

| Goal | Document |
| --- | --- |
| Understand how the pieces fit together | [architecture.md](architecture.md) |
| Run the platform with Docker | [docker.md](docker.md) |
| Run the platform without Docker on Windows, macOS or Linux | [local-setup.md](local-setup.md) |
| Run every test and understand what it proves | [testing.md](testing.md) |
| Review the security design | [security.md](security.md) |
| Fix a problem that came up during set-up or measurement | [troubleshooting.md](troubleshooting.md) |
| See exactly what was run and what was not | [verification-status.md](verification-status.md) |

## Component documents

| Component | Document |
| --- | --- |
| Laravel API, all versions, endpoints, authorization | [ECBackend/README.md](../ECBackend/README.md) |
| Version 3 read path, cache, ETag, replica, lab API | [ECBackend/README.md](../ECBackend/README.md#performance-and-caching-v3) |
| Go inventory service | [ECInventory/README.md](../ECInventory/README.md) |
| gRPC contract | [proto/README.md](../proto/README.md) |
| k6 scripts | [loadtest/README.md](../loadtest/README.md) |
| Measurement procedure and recorded results | [PERFORMANCE.md](../PERFORMANCE.md) |
| Postman collections | [ECBackend/README.md](../ECBackend/README.md#postman-collection) |

## Reading order for a reviewer

1. [architecture.md](architecture.md) for the design and the reasons behind it.
2. [ECInventory/README.md](../ECInventory/README.md) for requirement 2.
3. [PERFORMANCE.md](../PERFORMANCE.md) for requirement 3 and its measured numbers.
4. [verification-status.md](verification-status.md) for the evidence and its limits.

## Conventions

- Commands are written for PowerShell on Windows unless the block is marked `bash`.
- Every command block can be copied and run as it is, from the folder named in the sentence above it.
- Code blocks contain no comments. Explanations are in the text around them.
