<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\FedExServiceFixed::class, function ($app) {
            return new \App\Services\FedExServiceFixed();
        });

        // Keep the original service for backward compatibility
        $this->app->singleton(\App\Services\FedExService::class, function ($app) {
            return new \App\Services\FedExService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
