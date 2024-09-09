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
            __DIR__ . '/../config' => config_path()
        ]);
        $this->mergeConfigFrom(__DIR__ . '/../config/influx.php', 'grapher.backends');
    }

    public function register()
    {
        Config::set('grapher.providers.influx', InfluxGrapher::class);
    }
}
