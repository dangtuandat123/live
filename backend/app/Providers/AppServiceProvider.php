<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Fix SSL cert trên Windows/XAMPP — php.ini chưa set curl.cainfo
        $caBundle = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
        if (file_exists($caBundle) && empty(ini_get('curl.cainfo'))) {
            Http::globalOptions([
                'verify' => $caBundle,
            ]);
        }
    }
}
