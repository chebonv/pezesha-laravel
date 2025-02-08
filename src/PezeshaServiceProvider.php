<?php

namespace Chebon\PezeshaLaravel;

use Illuminate\Support\ServiceProvider;

/* 
 * Service provider for the Pezesha Laravel package
 */
class PezeshaServiceProvider extends ServiceProvider
{
    /* 
     * Boot method for the service provider
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/Config/pezesha.php' => config_path('pezesha.php'),
        ], 'config');
    }

    /* 
     * Register method for the service provider
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/pezesha.php', 'pezesha'
        );

        $this->app->singleton('pezesha', function ($app) {
            return new Pezesha();
        });
    }
} 