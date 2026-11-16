# Documentation index

> Part of the [ECommerce](../../README.md) repository, requirements 2 and 3 ([FReq_02_03](../README.md)).

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
