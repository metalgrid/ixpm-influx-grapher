<?php

namespace Metalgrid\InfluxGrapher\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Metalgrid\InfluxGrapher\Services\Grapher\InfluxGrapher;

class InfluxGrapherProvider extends ServiceProvider
{
    public function boot()
    {
        $this->addPublishGroup("metalgrid:influx", [
            __DIR__ . '/../config' => config_path(),
            __DIR__ . '/../skins' => resource_path('skins'),
            __DIR__ . '/../js' => public_path('influx/js')
        ]);
        $this->mergeConfigFrom(__DIR__ . '/../config/influx.php', 'grapher.backends');
    }

    public function register()
    {
        Config::set('grapher.providers.influx', InfluxGrapher::class);
    }
}
