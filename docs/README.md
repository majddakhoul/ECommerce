# Documentation

This folder is the place to start if you want to run the project, understand how it works, or see what we measured. Each of the five requirements also has its own README next to its code, and those go into more detail. The pages here tie everything together.

[Repository](../README.md) · [Ideas and design](ideas-and-design.md) · [Environment setup](environment-setup.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Problems and solutions](problems-and-solutions.md) · [Troubleshooting](troubleshooting.md)

## The project in a few lines

ECommerce is a small online shop that we built in five steps. Each step keeps the previous one working and adds something new.

1. A Laravel REST API with JWT login, roles, products, carts and orders, plus a Flutter app.
2. The stock moves out of Laravel into its own Go service, called over gRPC.
3. Caching, indexes and other speed-ups, each one measured.
4. Background jobs through RabbitMQ and live updates through WebSockets, with a React web client.
5. Three copies of the API behind an nginx load balancer, sharing state through Redis.

## Where to go

| If you want to... | Read |
| --- | --- |
| Understand what each step does and why we built it that way | [ideas-and-design.md](ideas-and-design.md) |
| Install what you need | [environment-setup.md](environment-setup.md) |
| Run a requirement, with or without Docker | [requirement-1.md](requirement-1.md), [requirement-2-3.md](requirement-2-3.md), [requirement-4.md](requirement-4.md), [requirement-5.md](requirement-5.md) |
| Find a command | [commands.md](commands.md) |
| See the numbers we measured, and what they do not prove | [results.md](results.md) |
| Read about what went wrong along the way | [problems-and-solutions.md](problems-and-solutions.md) |
| Fix something that is broken right now | [troubleshooting.md](troubleshooting.md) |

If you are new to the project, read the ideas page first, then install the tools, then run requirement 5 with Docker. It is the quickest way to see everything working at once.

## Which folder to run

Each requirement is a complete, separate copy of the project. Pick one folder and work inside it.

| Folder | What you get |
| --- | --- |
| `FReq_01` | The Laravel API and the Flutter app |
| `FReq_02_03` | The API plus the Go inventory service, with the performance work |
| `FReq_04` | Queues, WebSockets and the React client |
| `FReq_05` | Everything from the previous steps, running as three instances behind nginx |

Run one Docker stack at a time. Several of them use the same ports, and the [port table](environment-setup.md#ports-used-by-each-stack) shows which.

## Conventions

- Commands are written for PowerShell on Windows. On Linux and macOS the same commands work with the usual path changes.
- A command is always given together with the folder it must be run from.
- Secrets live in `.env` files. Those files are never committed. Copy `.env.example` and fill in your own values.
- Source code has no comments. The names explain the code and the documentation explains the reasons.
