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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            str_starts_with(config('app.url'), 'https://') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        if (!app()->runningInConsole()) {
            \Illuminate\Support\Facades\View::share('settings', \App\Models\AppSetting::first());
        }
    }
}
