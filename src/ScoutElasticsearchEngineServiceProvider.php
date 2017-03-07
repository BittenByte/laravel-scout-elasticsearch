<?php

namespace BittenByte\ScoutElasticsearchEngine;

use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use BittenByte\ScoutElasticsearchEngine\Engines\ElasticsearchEngine;

class ScoutElasticsearchEngineServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        resolve(EngineManager::class)->extend('elasticsearch', function () {
            return new ElasticsearchEngine(config('scout.elasticsearch.config'));
        });
    }

    /**
     * Register any package services.
     */
    public function register()
    {
    }
}
