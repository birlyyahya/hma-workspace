<?php

namespace App\Providers;

use App\Services\DarCache;
use App\Services\IzinCache;
use App\Services\ProjectCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
    */
    public function register(): void
    {
        $this->app->singleton(ProjectCache::class, fn () => new ProjectCache(
            rtrim((string) config('services.api_project'), '/')
        ));

        $this->app->singleton(DarCache::class, fn () => new DarCache(
            rtrim((string) config('services.api_izin'), '/')
        ));

        $this->app->singleton(IzinCache::class, fn () => new IzinCache(
            rtrim((string) config('services.api_izin'), '/')
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            if (config('app.url')) {
                URL::forceRootUrl(config('app.url'));

                // Paksa https jika APP_URL https (perbaiki mixed-content di balik proxy)
                if (str_starts_with(config('app.url'), 'https')) {
                    URL::forceScheme('https');
                }
            }
        }

        Gate::before(function ($user, string $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            $names = Cache::remember(
                "user:{$user->id}:permissions",
                now()->addMinutes(30),
                fn () => $user->role?->permissions()->pluck('name')->all() ?? []
            );

            return \in_array($ability, $names, true) ? true : null;
        });
    }
}
