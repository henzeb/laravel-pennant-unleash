<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Configuration;

use Closure;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\CacheInterface;
use Unleash\Client\Metrics\DefaultMetricsHandler;
use Unleash\Client\Metrics\MetricsHandler;
use Unleash\Client\Strategy\StrategyHandler;
use Unleash\Client\UnleashBuilder;
use Unleash\Client\Variant\DefaultVariantHandler;
use Unleash\Client\Variant\VariantHandler;

class UnleashClientBuilder
{
    public function build(UnleashBuilder $builder, Closure $customize): UnleashBuilder
    {
        $builder = $builder
            ->withAppUrl(config()->string('unleash.app_url', ''))
            ->withInstanceId(config()->string('unleash.instance_id', ''))
            ->withAppName(config()->string('unleash.app_name', ''))
            ->withHeader('Authorization', config()->string('unleash.api_key', ''))
            ->withCacheHandler(
                Cache::store(config()->string('unleash.cache.driver')),
                config()->integer('unleash.cache.ttl', 15)
            )
            ->withStaleCacheHandler($this->resolveStaleCacheHandler())
            ->withStaleTtl(config()->integer('unleash.cache.stale_ttl', 30 * 60))
            ->withMetricsEnabled(config()->boolean('unleash.metrics.enabled', true))
            ->withMetricsInterval(config()->integer('unleash.metrics.interval', 60_000))
            ->withMetricsHandler($this->resolveMetricsHandler())
            ->withVariantHandler($this->resolveVariantHandler());

        if (config()->boolean('unleash.events', false)) {
            $builder = $builder->withEventSubscriber(new UnleashEventDispatcher());
        }

        foreach ($this->resolveStrategies() as $strategy) {
            $builder = $builder->withStrategy($strategy);
        }

        $builder = $customize($builder);

        if (config()->boolean('unleash.development', false)) {
            $bootstrapFile = config('unleash.bootstrap_file');

            $builder = $builder
                ->withFetchingEnabled(false)
                ->withBootstrapFile(is_string($bootstrapFile) ? $bootstrapFile : null);
        }

        return $builder;
    }

    /**
     * @return array<StrategyHandler>
     */
    private function resolveStrategies(): array
    {
        return array_map(
            static fn(string $strategy) => resolve($strategy),
            config()->array('unleash.strategies', [])
        );
    }

    private function resolveMetricsHandler(): ?MetricsHandler
    {
        $handler = config()->string('unleash.metrics.handler', DefaultMetricsHandler::class);

        return $handler === DefaultMetricsHandler::class ? null : resolve($handler);
    }

    private function resolveVariantHandler(): ?VariantHandler
    {
        $handler = config()->string('unleash.variant_handler', DefaultVariantHandler::class);

        return $handler === DefaultVariantHandler::class ? null : resolve($handler);
    }

    private function resolveStaleCacheHandler(): ?CacheInterface
    {
        $driver = config('unleash.cache.stale_driver');

        return is_string($driver) && $driver !== '' ? Cache::store($driver) : null;
    }
}
