<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('moderate', fn (User $user) => $user->canModerate());
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Zips are built on the fly and can be gigabytes, so keep them sane.
        RateLimiter::for('zips', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(60)->by($request->user()?->id ?: $request->ip()));
    }
}
