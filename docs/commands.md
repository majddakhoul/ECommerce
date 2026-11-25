# Commands

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

Every command of the project, grouped by task. The sentence above each block names the folder to run it from. The explanation of each command is on the page of its requirement.

## Check the tools

From any folder:

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

## Secrets

From any folder:

```powershell
docker run --rm php:8.3-cli php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

## Docker stacks

From the folder of the requirement: `FReq_01/ECBackend`, `FReq_02_03`, `FReq_04` or `FReq_05`.

```powershell
Copy-Item .env.example .env
```

```powershell
docker compose up --build
```

```powershell
docker compose up -d
```

```powershell
docker compose ps
```

```powershell
docker compose ps -a
```

```powershell
docker compose logs -f app1 app2 app3
```

```powershell
docker compose logs --tail 40 worker1 worker2 worker3
```

```powershell
docker compose down
```

```powershell
docker compose down -v
```

In `FReq_05`, stop everything including Mailpit and the k6 container, so the network is released:

```powershell
docker compose --profile mail --profile tools down
```

Start the mail catcher together with the stack:

```powershell
docker compose --profile mail up -d
```

Stop and start one instance, to watch nginx route around it:

```powershell
docker compose stop app2
```

```powershell
docker compose start app2
```

Open a shell or run one command inside a container:

```powershell
docker compose exec app1 sh
```

```powershell
docker compose exec app1 printenv MAIL_MAILER MAIL_HOST MAIL_PORT
```

Remove a leftover container of a local set-up that holds a port:

```powershell
docker rm -f ec5_rabbitmq
```

Rebuild one image after a change in the backend code:

```powershell
docker compose up --build -d
```

## Databases

From any folder, with MySQL on the default port:

```powershell
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS inventory CHARACTER SET utf8mb4;"
```

## Laravel

From the `ECBackend` folder of the requirement.

```powershell
composer install
```

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

```powershell
php artisan jwt:secret
```

```powershell
php artisan migrate --seed
```

```powershell
php artisan migrate:fresh --seed
```

```powershell
php artisan storage:link
```

```powershell
php artisan serve --port=8000
```

```powershell
php artisan config:clear
```

Requirements 4 and 5 only:

```powershell
php artisan messaging:topology
```

```powershell
php artisan reverb:start
```

```powershell
php artisan queue:work rabbitmq --queue=emails,broadcasts --tries=3
```

```powershell
php artisan inventory:consume
```

Requirement 3 only, inside the container or locally:

```powershell
php artisan perf:seed --products=50000 --orders=200000
```

```powershell
php artisan perf:indexes add
```

```powershell
php artisan perf:indexes drop
```

## Inventory service

From the `ECInventory` folder.

```powershell
go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.36.6
go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.5.1
New-Item -ItemType Directory -Force gen/inventorypb
protoc --proto_path=../proto --go_out=gen/inventorypb --go_opt=paths=source_relative --go-grpc_out=gen/inventorypb --go-grpc_opt=paths=source_relative ../proto/inventory.proto
```

```powershell
go mod tidy
```

```powershell
$env:DB_USER="root"; $env:DB_PASSWORD=""; $env:DB_NAME="inventory"; $env:INVENTORY_API_KEY="change-me"; $env:RABBITMQ_HOST="127.0.0.1"
```

```powershell
go run ./cmd/server
```

```powershell
$env:APP_DB_NAME="auth_system"
```

```powershell
go run ./cmd/seed
```

Check that the service listens:

```powershell
Test-NetConnection 127.0.0.1 -Port 50051
```

## Web client

From the `ECWeb` folder of requirement 4 or 5.

```powershell
npm install
```

```powershell
npm run dev
```

```powershell
npm run build
```

## Flutter client

From `FReq_01/ECFrontend`.

```powershell
flutter pub get
```

```powershell
flutter analyze
```

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

## Tests

From the `ECBackend` folder of the requirement:

```powershell
php artisan test
```

```powershell
php artisan test --filter=V5
```

From the `ECInventory` folder:

```powershell
go test -race ./internal/...
```

```powershell
go test -tags integration -count=1 -v ./tests/integration/...
```

From the `ECWeb` folder:

```powershell
npm test
```

```powershell
npm run typecheck
```

From `FReq_01/ECFrontend`:

```powershell
flutter test
```

## Load tests

Requirement 2, from `FReq_02_03`:

```powershell
docker compose --profile tools run --rm k6 run /scripts/race.js
```

Requirement 3, from `FReq_02_03`:

```powershell
docker compose --profile tools run --rm -e VERSION=v2 -e LABEL=step0-v2-baseline -e VUS=20 -e DURATION=60s k6 run /scripts/compare.js
```

```powershell
docker compose --profile tools run --rm k6 run /scripts/consistency.js
```

Requirement 4, from `FReq_04`:

```powershell
docker compose run --rm -e MODE=sync -e LABEL=sync k6 run /scripts/orders.js
```

```powershell
docker compose run --rm -e MODE=async -e LABEL=async k6 run /scripts/orders.js
```

Requirement 5, from `FReq_05`:

```powershell
docker compose --profile tools run --rm k6 run /scripts/scale.js
```

```powershell
docker compose --profile tools run --rm -e WORKLOAD=order -e TOTAL=50 -e SECONDS=30 k6 run /scripts/scale.js
```

```powershell
docker compose --profile tools run --rm k6 run /scripts/cart.js
```

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

```powershell
.\loadtest\failover.ps1
```

```powershell
Invoke-RestMethod -Method Delete http://localhost:8025/api/v1/messages
```

```powershell
docker compose --profile mail --profile tools run --rm -e WAIT_SECONDS=30 k6 run /scripts/mail.js
```

## Check the distribution by hand

From `FReq_05`, with a token in `$token`:

```powershell
1..12 | ForEach-Object { (Invoke-WebRequest http://localhost:8000/api/v4/async/products -Headers @{Authorization="Bearer $token"}).Headers['X-Served-By'] }
```

## Addresses

| Address | What |
| --- | --- |
| `http://localhost:8000` | API (nginx in requirement 5) |
| `http://localhost:5173` | React web client |
| `http://localhost:15672` | RabbitMQ management, `guest` and `guest` |
| `http://localhost:8025` | Mailpit, with the `mail` profile |
| `http://localhost:8080` | phpMyAdmin in requirements 1 to 3, Reverb in requirements 4 and 5 |
