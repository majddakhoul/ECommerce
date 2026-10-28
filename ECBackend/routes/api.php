<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('auth.register');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:api', 'active'])->group(function () {

        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('/auth/me', [AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::put('/auth/password', [AuthController::class, 'changePassword'])->name('auth.password');
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        Route::post('/users/{user}/roles', [UserController::class, 'assignRole'])->name('users.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole'])->name('users.roles.remove');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/categories', [ProductController::class, 'categories'])->name('products.categories');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
        Route::put('/cart/items/{product}', [CartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('/cart/items/{product}', [CartController::class, 'removeItem'])->name('cart.items.remove');
        Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
        Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/my', [OrderController::class, 'mine'])->name('orders.mine');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('/roles/{role}/users', [RoleController::class, 'users'])->name('roles.users');
    });
});

Route::fallback(function () {
    return response()->json([
        'status' => false,
        'code' => 404,
        'message' => 'The requested endpoint does not exist.',
        'data' => null,
    ], 404);
})->name('fallback');
