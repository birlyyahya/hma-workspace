<?php

namespace App\Providers;

use App\Services\ProjectCache;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
