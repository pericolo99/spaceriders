<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
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
        // In produzione il database è condiviso con il vecchio pannello:
        // migrate:fresh, db:wipe e simili cancellerebbero anche le sue tabelle.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
