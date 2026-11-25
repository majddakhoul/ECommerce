# Environment setup

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

This page lists every tool the project can use, which requirement needs it, how to install it on Windows and the command that proves it works. Install only what the way you chose to run the project needs.

## Choose the way to run

| Way | Needs | Best for |
| --- | --- | --- |
| Docker only | Git, Docker Desktop | Requirements 4 and 5, and any quick demonstration. Nothing else is installed on the machine |
| Local, without Docker | Git, PHP, Composer, MySQL, and for later requirements Go, protoc, Node.js, RabbitMQ | Debugging code, running single tests, the path used during development for requirements 1 to 4 |
| Mobile client | Flutter, an Android emulator or a phone, optionally ngrok | Requirement 1 client |
| Load tests | k6, or Docker (the k6 image is used by the compose files) | Requirements 3, 4 and 5 |

## Tool table

| Tool | Version | Needed by | Purpose |
| --- | --- | --- | --- |
| Git | 2.40 or newer | All | Clone and publish |
| Docker Desktop | Compose v2 | Docker runs | Containers. On Windows it needs WSL 2 |
| PHP | 8.2 or newer (the images use 8.3) | Local runs | Laravel. Extensions: `pdo_mysql`, `mbstring`, `zip`, `sockets`, `redis` (optional), `grpc` (requirements 2 to 5) |
| Composer | 2 | Local runs | PHP dependencies |
| MySQL or MariaDB | 8 recommended | Local runs | The API database, and a second database for inventory from requirement 2 |
| Go | 1.24 | Local runs of requirements 2 to 5 | Inventory service |
| protoc and two Go plugins | `protoc` 36.2 was used. `protoc-gen-go` v1.36.6, `protoc-gen-go-grpc` v1.5.1 | Local runs of requirements 2 to 5 | Generate the gRPC code |
| Node.js | 20 or newer (the image uses 22) | Requirements 4 and 5 | React web client |
| RabbitMQ | 3.13 | Requirements 4 and 5 | Queue broker |
| Redis | 7 | Requirement 3 optional, requirement 5 for several instances | Cache, cart, locks |
| nginx | 1.30.2 (inside Docker) | Requirement 5 | Load balancer. For local use any recent nginx works |
| k6 | 0.54.0 (the compose files use `grafana/k6:0.54.0`) | Requirements 3, 4 and 5 | Load tests |
| Mailpit | v1.20 (inside Docker) | Requirement 5 mail audit | Catches mail so it can be counted |
| Flutter | 3.44 with Dart 3.12 | Requirement 1 client | Mobile client |
| Postman | Current | All | Import the collections |
| ngrok | Current | Optional | Expose the API to a phone |

## Windows install commands

Open PowerShell as the user (administrator rights are not needed except for Docker Desktop). If `winget` does not know an identifier, find it with `winget search <name>`.

```powershell
winget install Git.Git
winget install Docker.DockerDesktop
winget install OpenJS.NodeJS.LTS
winget install GoLang.Go
winget install protobuf
winget install k6 --source winget
winget install Postman.Postman
```

Close and reopen PowerShell after each group so that the `PATH` is refreshed.

### Docker Desktop

Install it, start it, and wait until the whale icon says it is running. When it asks for WSL 2, accept. Then:

```powershell
docker --version
docker compose version
docker run --rm hello-world
```

The last command prints a greeting when the engine works.

### PHP and MySQL

XAMPP was used for development, which gives PHP and MySQL in one installer. Download it from `https://www.apachefriends.org`, choose a build with PHP 8.2 or newer and start MySQL from its control panel. Add the PHP folder (for XAMPP, `C:\xampp\php`) to the `PATH`.

Enable the extensions in `php.ini` by removing the semicolon in front of each line:

```text
extension=mbstring
extension=pdo_mysql
extension=zip
extension=sockets
extension=fileinfo
extension=curl
extension=openssl
```

The `grpc` extension is not bundled. Download the DLL that matches your PHP version and thread safety from `https://pecl.php.net/package/grpc`, copy it to the `ext` folder and add `extension=grpc` to `php.ini`. The Docker images compile it themselves, which is why the first build is slow, see [Docker first build](troubleshooting.md#docker).

```powershell
php -v
php -m
```

`php -m` must list `pdo_mysql`, `mbstring`, `zip`, `sockets` and, for requirements 2 to 5, `grpc`.

### Composer

Download the installer from `https://getcomposer.org/download` and run it. Choose the PHP you installed above.

```powershell
composer --version
```

### MySQL

With XAMPP the server listens on 3306 with the user `root` and no password. Check it:

```powershell
mysql -uroot -e "SELECT VERSION();"
```

### Go and the code generators

```powershell
go version
go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.36.6
go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.5.1
protoc --version
```

The generators are installed in `%USERPROFILE%\go\bin`. That folder must be on the `PATH`, otherwise `protoc` reports that `protoc-gen-go` is not found. The project Makefile needs `make`, which Windows does not have, so the documents give the same commands to run by hand, for example in [requirement-2-3.md](requirement-2-3.md#run-locally).

### Node.js

```powershell
node --version
npm --version
```

### RabbitMQ

The simplest way is a container, which works with every way of running the PHP code:

```powershell
docker run -d --name ec_rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3.13-management
```

Open `http://localhost:15672` and sign in with `guest` and `guest`. A native install needs Erlang/OTP first and then RabbitMQ from the official installers, followed by `rabbitmq-plugins enable rabbitmq_management`.

### Redis

Redis has no official Windows build. Use a container:

```powershell
docker run -d --name ec_redis -p 6379:6379 redis:7-alpine
```

Requirements 2 and 3 can run without Redis by using the file cache, see [requirement-2-3.md](requirement-2-3.md#differences-between-local-and-docker). Requirement 5 uses Redis inside its compose file and needs nothing installed.

### k6

```powershell
k6 version
```

The compose files also run k6 from an image, so a local install is optional.

### Flutter

Install Flutter from `https://docs.flutter.dev/get-started/install/windows` and add Android Studio for the SDK and an emulator.

```powershell
flutter doctor
flutter doctor --android-licenses
```

### ngrok

Needed only when a phone must reach the API on your computer.

```powershell
ngrok http 8000
```

## Ports used by each stack

The Docker stacks publish these host ports. Two stacks that share a port cannot run at the same time.

| Port | Requirement 1 | Requirements 2 and 3 | Requirement 4 | Requirement 5 |
| --- | --- | --- | --- | --- |
| 8000 | API | API | API | nginx, the entry point |
| 5173 | none | none | Web client | Web client |
| 8080 | phpMyAdmin | phpMyAdmin | Reverb | Reverb |
| 3307 | API database | API database | API database | API database |
| 3308 | none | Inventory database | Inventory database | Inventory database |
| 3309 | none | Read replica | none | none |
| 6379 | none | Redis | Redis | Redis |
| 5672 and 15672 | none | none | RabbitMQ and its interface | RabbitMQ and its interface |
| 50051 | none | gRPC inventory | gRPC inventory | gRPC inventory |
| 8025 | none | none | Mailpit, profile `mail` | Mailpit, profile `mail` |

Stop one stack before starting another:

```powershell
docker compose down
```

Run it from the folder of the stack that is running. To change a port instead, set the variable named in the `.env.example` of that folder, see [configuration.md](../FReq_05/docs/configuration.md#docker-compose).

## Secrets

Secrets are generated on your machine and never committed.

```powershell
docker run --rm php:8.3-cli php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Use the output for `JWT_SECRET`, for `REVERB_APP_SECRET`, and with the prefix `base64:` for `APP_KEY`. Keep `.env` out of version control and out of anything you share. A secret that was shared once must be replaced.

## Check that everything is ready

```powershell
git --version
docker compose version
php -v
composer --version
go version
protoc --version
node --version
k6 version
```

Every line must print a version. Install the tools of the lines that fail only if the requirement you want to run needs them, using the [tool table](#tool-table).

## Next

Continue with the page of the requirement: [requirement 1](requirement-1.md), [requirements 2 and 3](requirement-2-3.md), [requirement 4](requirement-4.md) or [requirement 5](requirement-5.md).
