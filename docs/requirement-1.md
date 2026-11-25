# Requirement 1: REST API and mobile client

[Repository](../README.md) · [Documentation index](README.md) · [Environment setup](environment-setup.md) · [Ideas and design](ideas-and-design.md) · [Problems and solutions](problems-and-solutions.md) · [Requirement 1](requirement-1.md) · [Requirements 2 and 3](requirement-2-3.md) · [Requirement 4](requirement-4.md) · [Requirement 5](requirement-5.md) · [Commands](commands.md) · [Results](results.md) · [Troubleshooting](troubleshooting.md)

Requirement 1 is the foundation of the platform: a Laravel 12 REST API under `/api/v1` and a Flutter client that uses every endpoint. The code is in [FReq_01](../FReq_01/README.md).

## What it contains

| Part | Folder | Documentation |
| --- | --- | --- |
| Laravel API with JWT, roles, users, products with images, cart, orders, stock reservation, order mail, login lockout, rate limiting | [FReq_01/ECBackend](../FReq_01/ECBackend/README.md) | [Backend README](../FReq_01/ECBackend/README.md) |
| Flutter client with Cubit state management, Dio and GetX | [FReq_01/ECFrontend](../FReq_01/ECFrontend/README.md) | [Frontend README](../FReq_01/ECFrontend/README.md) |
| Postman collections | [postman](../FReq_01/ECBackend/postman) | [Postman section](../FReq_01/ECBackend/README.md#postman-collection) |

## Prerequisites

Install the tools named here, with the commands in [environment-setup.md](environment-setup.md).

| Way | Tools |
| --- | --- |
| Docker | Git, Docker Desktop |
| Local | Git, PHP 8.2 or newer, Composer, MySQL |
| Mobile client | Flutter 3.44, Android SDK or an emulator, optionally ngrok |

## Run with Docker

From `FReq_01/ECBackend`:

```powershell
Copy-Item .env.example .env
```

```powershell
docker compose up --build
```

Three containers start: the API on `http://localhost:8000`, MySQL 8 on host port 3307 (database `ecommerce`) and phpMyAdmin on `http://localhost:8080`. The entrypoint runs the migrations, the seeders and `storage:link` before the server starts. Only the database connection is overridden to point at the `db` container.

Stop and clean:

```powershell
docker compose down
```

```powershell
docker compose down -v
```

The second command also removes the database, the uploaded images and the logs, which live in named volumes.

## Run locally

Create the database first:

```powershell
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS auth_system CHARACTER SET utf8mb4;"
```

From `FReq_01/ECBackend`:

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

Check in `.env` that `DB_HOST=127.0.0.1`, `DB_DATABASE=auth_system`, `DB_USERNAME=root` and `DB_PASSWORD` is empty. Then:

```powershell
php artisan migrate:fresh --seed
```

```powershell
php artisan storage:link
```

```powershell
php artisan serve --port=8000
```

If a stale `composer.lock` makes `composer install` fail, delete it and run `composer update`.

Open `http://localhost:8000/api/v1/products` after logging in. The demo accounts, with the password `password`, are `admin@example.com` and `user@example.com`.

Mail uses `MAIL_MAILER=log` by default, so every message is written to `storage/logs/laravel.log`. To see rendered mail, point `MAIL_HOST` and `MAIL_PORT` at an SMTP catcher and set `MAIL_MAILER=smtp`.

## Run the Flutter client

From `FReq_01/ECFrontend`:

```powershell
flutter pub get
```

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

| Target | Value of `API_BASE_URL` |
| --- | --- |
| Android emulator | `http://10.0.2.2:8000` |
| Windows or Chrome | `http://127.0.0.1:8000` |
| Physical phone | An ngrok address from `ngrok http 8000`, or the LAN address of the computer |

More options are in the [frontend documentation](../FReq_01/ECFrontend/README.md#api-address).

## Test

From `FReq_01/ECBackend`:

```powershell
php artisan test
```

The tests use an in-memory SQLite database, so the `pdo_sqlite` extension must be enabled. The suite has 107 test methods in `AuthTest`, `LoginLockoutTest`, `UserTest`, `RoleTest`, `ProductTest`, `CartTest` and `OrderTest`, described in the [testing section](../FReq_01/ECBackend/README.md#testing).

From `FReq_01/ECFrontend`:

```powershell
flutter analyze
```

```powershell
flutter test
```

## Postman

Import [`ECommerce.postman_collection.json`](../FReq_01/ECBackend/postman/ECommerce.postman_collection.json) (41 requests grouped into Auth, Users, Roles, Products, Cart and Orders). Log in once with either demo account and a collection script stores the token. [`Auth-Roles.postman_collection.json`](../FReq_01/ECBackend/postman/Auth-Roles.postman_collection.json) holds 28 requests and was kept from the earlier authentication stage of the project. Use the first collection for the full flows.

## Verify

| Check | How | Expected |
| --- | --- | --- |
| The API answers | Log in as `admin@example.com` | `200` with `access_token` |
| Stock is reserved | Place an order, read the product | The quantity dropped by the ordered amount |
| Stock is restored | Cancel the order | The quantity returned |
| Lockout | Fail five logins in a row | `423` for the following attempts, even with the right password |
| Roles | A user calls an admin endpoint | `403` |

## Results

No measurement belongs to this requirement. Its evidence is the automated suite and the Postman flows, listed in [results.md](results.md#requirement-1). The Flutter client of this requirement was not retested against the later routes, see [results.md](results.md#not-demonstrated).

## Continue

[Requirements 2 and 3](requirement-2-3.md) move stock to the Go service and add caching, without changing the contract of `/api/v1`.
