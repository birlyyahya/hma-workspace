<?php

namespace App\Providers;

use App\Models\User;
use App\Services\DarCache;
use App\Services\DarWriter;
use App\Services\IzinCache;
use App\Services\IzinWriter;
use App\Services\ProjectCache;
use App\Services\ProjectWriter;
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

        $this->app->singleton(DarWriter::class, fn ($app) => new DarWriter(
            rtrim((string) config('services.api_izin'), '/'),
            $app->make(DarCache::class),
        ));

        $this->app->singleton(IzinWriter::class, fn ($app) => new IzinWriter(
            rtrim((string) config('services.api_izin'), '/'),
            $app->make(IzinCache::class),
        ));

        $this->app->singleton(ProjectWriter::class, fn ($app) => new ProjectWriter(
            rtrim((string) config('services.api_project'), '/'),
            $app->make(ProjectCache::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('super-admin') || $user->hasPermission('view.pulse');
        });

        Gate::define('viewActivityLog', function (User $user) {
            return $user->hasRole('super-admin') || $user->hasPermission('activitylog.view');
        });

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
