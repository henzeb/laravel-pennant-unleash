<?php

namespace Henzeb\Pennant\Unleash\Providers;

use Closure;
use Henzeb\Pennant\Unleash\Drivers\UnleashDriver;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Drivers\Decorator;
use Laravel\Pennant\Feature;
use Unleash\Client\Stickiness\StickinessCalculator;
use Unleash\Client\Stickiness\MurmurHashCalculator;
use Unleash\Client\UnleashBuilder;

class PennantUnleashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/unleash.php', 'unleash');

        $this->app->bind(StickinessCalculator::class, MurmurHashCalculator::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/unleash.php' => config_path('unleash.php'),
        ], 'laravel-pennant-unleash');


        Feature::macro('buildUnleashClientUsing', function (?Closure $callback) {
            /**
             * @var $this Decorator
             */
            if ($this->getDriver() instanceof UnleashDriver) {
                $this->getDriver()->buildUnleashClientUsing($callback);
            }
        });

        Feature::macro('resolveUnleashContextUsing', function (?Closure $callback) {
            /**
             * @var $this Decorator
             */
            if ($this->getDriver() instanceof UnleashDriver) {
                $this->getDriver()->resolveUnleashContextUsing($callback);
            }
        });

        Feature::extend('unleash', function () {
           return new UnleashDriver(UnleashBuilder::create());
        });
    }
}
