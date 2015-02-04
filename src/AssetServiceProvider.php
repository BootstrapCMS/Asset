<?php

namespace Lightgear\Asset;

use Illuminate\Support\ServiceProvider;
use Lightgear\Asset\Console\Commands\CleanAssets;
use Lightgear\Asset\Console\Commands\GenerateAssets;

class AssetServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->setupConfig();

        $this->commands('asset.clean', 'asset.generate');
    }

    /**
     * Setup the config.
     *
     * @return void
     */
    protected function setupConfig()
    {
        $source = realpath(__DIR__.'/../config/asset.php');

        $this->publishes([$source => config_path('asset.php')]);

        $this->mergeConfigFrom($source, 'asset');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('asset', function () {
            return new Asset();
        });

        $this->app->singleton('asset.clean', function () {
            return new CleanAssets();
        });

        $this->app->singleton('asset.generate', function () {
            return new GenerateAssets();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [
            'asset',
            'asset.clean',
            'asset.generate',
        ];
    }
}
