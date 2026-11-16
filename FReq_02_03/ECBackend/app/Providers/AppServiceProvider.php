<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;
use App\Support\Performance\CatalogCache;
use App\Support\Performance\Flags;
use App\Support\Performance\ReplicaGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Flags::class);
        $this->app->singleton(CatalogCache::class);
        $this->app->singleton(ReplicaGuard::class);
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('api.rate_limit'))->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by($email . '|' . $request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
