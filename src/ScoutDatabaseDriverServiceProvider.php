<?php

namespace Sanjab;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ScoutDatabaseDriverServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        resolve(EngineManager::class)->extend('database', function () {
            return new DatabaseEngine($this->app['db']);
        });
    }
}
