# ECommerce Flutter Client

> Part of the [ECommerce](../README.md) project. The API it talks to lives in [`ECBackend`](../ECBackend/README.md).

<p align="center">
<img src="https://img.shields.io/badge/Flutter-3.44-02569B?logo=flutter" alt="Flutter 3.44">
<img src="https://img.shields.io/badge/Dart-3.12-0175C2?logo=dart" alt="Dart 3.12">
<img src="https://img.shields.io/badge/State-Cubit%20%2F%20BLoC-5B2EFF" alt="Cubit / BLoC">
<img src="https://img.shields.io/badge/HTTP-Dio-0F9D58" alt="Dio">
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

---

## Table of Contents

- [Overview](#overview)
- [Screens and Features](#screens-and-features)
- [Design](#design)
- [Architecture](#architecture)
- [Project Structure](#project-structure)
- [Endpoint Coverage](#endpoint-coverage)
- [Installation and Setup](#installation-and-setup)
- [API Address](#api-address)
- [Android Notes](#android-notes)
- [Demo Accounts](#demo-accounts)
- [Testing](#testing)
- [Dependencies](#dependencies)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Overview

A Flutter client for the ECommerce Laravel API (`/api/v1`). Every endpoint of the Postman collection (41 requests) is wired to a Cubit and a screen. State management is Cubit / BLoC, navigation uses GetX, HTTP uses Dio, and the session token is stored with `shared_preferences`.

---

## Screens and Features

| Area | Features |
|---|---|
| **Onboarding and splash** | Three intro slides shown once, session check on start, retry on network error |
| **Authentication** | Login, registration, silent token refresh, automatic logout and redirect on `401` |
| **Home** | New In carousel, category chips, product grid, debounced search, in-stock filter, infinite scroll, pull to refresh |
| **Product details** | Add to cart and Buy now |
| **Cart** | Change quantity, remove, clear, checkout, live item badge on the navigation bar |
| **Orders** | My orders, order details, cancel, status chips |
| **Profile** | Edit name and email, change password, logout |
| **Admin** | Visible only to users with the `admin` role: users, roles, products (with image upload), and all orders with status updates |

---

## Design

The palette follows the reference mock-up: an indigo background, a lavender accent, soft pink product cards, black primary buttons, and large rounded corners with the purple quarter-circle motif.

| Token | Hex | Used for |
|---|---|---|
| `primary` | `#554CA1` | splash, onboarding, login and register header |
| `accent` | `#8B72D8` | chips, prices, highlights, blobs |
| `pink` | `#FCC9D2` | product backgrounds, empty states |
| `black` | `#000100` | main buttons |
| `danger` | `#FF1D48` | destructive actions |

---

## Architecture

```text
Page → Cubit → Dio client → API
          │
        State → BlocBuilder / BlocConsumer → Widgets
```

Each feature owns its `bloc/cubit`, `bloc/state`, `model`, pages, and widgets. Cubits return plain states, so screens only render states and never talk to the network. The Dio interceptor adds the bearer token to every request and, on a `401`, clears the token and broadcasts a logout event that sends the user back to the login screen.

---

## Project Structure

```text
lib/
├── main.dart
├── core/
│   ├── network/    api_constants, dio_client, api, api_response, api_exception
│   ├── storage/    secure_storage_service
│   ├── theme/      app_color, app_theme, app_input
│   ├── utils/      json_utils, validators, action_result
│   └── widgets/    custom_button, custom_from_field, app_top_bar, app_snack, state_views, product_image, ...
└── features/
    ├── splash_screen/  onboarding/  main_navigation/
    ├── authintication/ bloc (AuthCubit)  login/  register/  widget (AuthGate)
    ├── home/           bloc (HomeCubit)  model  widget  product_details/
    ├── cart/           bloc (CartCubit)  model  widget
    ├── orders/         bloc (OrdersCubit, OrderDetailsCubit)  model  widget  page
    ├── profile/        bloc (ProfileCubit)  page
    └── admin/          users/  roles/  products/  orders/
```

---

## Endpoint Coverage

| Area | Endpoints | Where |
|---|---|---|
| Auth | register, login, me, refresh, logout | `RegisterCubit`, `LoginCubit`, `AuthCubit` |
| Profile | update profile, change password | `ProfileCubit` |
| Products | list (search, category, in_stock, pagination), categories, show | `HomeCubit`, `ProductDetailsCubit` |
| Products (admin) | create, update (multipart image), delete | `ProductAdminCubit` |
| Cart | view, add, update quantity, remove, clear, checkout | `CartCubit` |
| Orders | my orders, place order, show, cancel | `OrdersCubit`, `OrderDetailsCubit` |
| Orders (admin) | list all, update status, delete | `OrdersCubit(adminScope: true)`, `OrderDetailsCubit` |
| Users (admin) | list, show, create, update, delete, unlock, assign role, remove role | `UsersCubit` |
| Roles (admin) | list, create, show, role users, update, delete | `RolesCubit` |

---

## Installation and Setup

**Prerequisites**
* Flutter 3.44 or newer (Dart 3.12)
* Android SDK with accepted licenses (`flutter doctor --android-licenses`) for Android builds
* A running ECBackend (see its [README](../ECBackend/README.md))

**Steps**

```bash
cd ECFrontend
flutter pub get
flutter analyze
flutter test
flutter run
```

Run on a specific target with `flutter run -d chrome`, `flutter run -d windows`, or `flutter run -d <device-id>`.

---

## API Address

The default base URL is the project tunnel `https://expectant-grumble-ungraded.ngrok-free.dev`. Change it without editing code:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

| Target | Address |
|---|---|
| Android emulator | `http://10.0.2.2:8000` |
| Windows or Chrome | `http://127.0.0.1:8000` |
| Physical phone | an ngrok URL (`ngrok http 8000`) or your computer's LAN address |

Every request sends the header `ngrok-skip-browser-warning` so that ngrok returns the API response instead of its interstitial page. Product image addresses returned by the API are rewritten to the configured host.

---

## Android Notes

The API may be served over plain HTTP, so allow it in `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<application
    android:usesCleartextTraffic="true"
    ... >
```

The project avoids plugins that pin an old Android Gradle Plugin, so it builds with AGP 9 and Gradle 9.

---

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

---

## Testing

```bash
flutter test
```

The unit tests cover the validators, model parsing, and parsing of `422` validation errors.

---

## Dependencies

| Package | Purpose |
|---|---|
| `flutter_bloc` | Cubit / BLoC state management |
| `dio` | HTTP client and interceptors |
| `get` | Navigation and `GetMaterialApp` |
| `shared_preferences` | Token and onboarding flag storage |
| `image_picker` | Product image selection |
| `cupertino_icons` | Icons |

---

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `The connection timed out` on a phone | `10.0.2.2` only works on the emulator. Use an ngrok URL and pass it with `--dart-define=API_BASE_URL=...`. |
| Responses are HTML instead of JSON | ngrok interstitial page. The `ngrok-skip-browser-warning` header is already sent; check that the tunnel points to port 8000. |
| Gradle cannot download `builder-x.jar` | Network or cache problem on `dl.google.com`. Clear `~/.gradle/caches/modules-2` and retry on another network. |
| Constants such as the base URL do not change | Hot reload does not re-evaluate them. Stop and run again. |
| Product images do not load | Run `php artisan storage:link` on the backend and make sure the tunnel is running. |

---

## License

This project is released under the [MIT License](LICENSE). Copyright (c) 2026 Majd AlDakhoul.
