<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Fetching\DnsHostResolver;
use App\Services\Fetching\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps($this->app->isProduction());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // API tokens are only issued to people who can write; the shared demo
        // account would otherwise let anyone mint tokens.
        Gate::define('manage-tokens', fn (User $user) => $user->hasRole('admin'));
    }
}
