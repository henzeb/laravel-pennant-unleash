<?php

use Henzeb\Pennant\Unleash\Configuration\UnleashClientBuilder;
use Henzeb\Pennant\Unleash\Configuration\UnleashEventDispatcher;
use Illuminate\Support\Arr;
use Tests\Fixtures\CustomMetricsHandler;
use Tests\Fixtures\CustomStrategyHandler;
use Tests\Fixtures\CustomVariantHandler;
use Tests\Fixtures\DependentStrategyHandler;
use Unleash\Client\Bootstrap\EmptyBootstrapProvider;
use Unleash\Client\Bootstrap\FileBootstrapProvider;
use Unleash\Client\Metrics\DefaultMetricsHandler;
use Unleash\Client\Strategy\DefaultStrategyHandler;
use Unleash\Client\UnleashBuilder;
use Unleash\Client\Variant\DefaultVariantHandler;

covers(UnleashClientBuilder::class);

/**
 * Removes the given unleash.* config keys entirely, as opposed to setting
 * them to null, so config()->string()/boolean() actually fall back to
 * their defaults instead of failing on an explicit null value.
 */
function forgetUnleashConfig(string ...$keys): void
{
    config()->set('unleash', Arr::except(config('unleash'), $keys));
}

function buildUnleashClient(?Closure $customize = null): UnleashBuilder
{
    return (new UnleashClientBuilder())->build(UnleashBuilder::create(), $customize ?? fn(UnleashBuilder $builder) => $builder);
}

function builderProperty(UnleashBuilder $builder, string $property): mixed
{
    $reflection = new ReflectionProperty($builder, $property);
    $reflection->setAccessible(true);

    return $reflection->getValue($builder);
}

it('passes the configured app url, instance id, app name and api key to the client builder', function () {
    config()->set('unleash.app_url', 'https://unleash.test');
    config()->set('unleash.instance_id', 'instance-1');
    config()->set('unleash.app_name', 'my-app');
    config()->set('unleash.api_key', 'secret-key');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'appUrl'))->toBe('https://unleash.test')
        ->and(builderProperty($builder, 'instanceId'))->toBe('instance-1')
        ->and(builderProperty($builder, 'appName'))->toBe('my-app')
        ->and(builderProperty($builder, 'headers'))->toBe(['Authorization' => 'secret-key']);
});

it('falls back to empty strings for the app url, instance id, app name and api key when not configured', function () {
    forgetUnleashConfig('app_url', 'instance_id', 'app_name', 'api_key');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'appUrl'))->toBe('')
        ->and(builderProperty($builder, 'instanceId'))->toBe('')
        ->and(builderProperty($builder, 'appName'))->toBe('')
        ->and(builderProperty($builder, 'headers'))->toBe(['Authorization' => '']);
});

it('passes the cache ttl to the client builder', function () {
    config()->set('unleash.cache.ttl', 42);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'cacheTtl'))->toBe(42);
});

it('defaults the cache ttl to 15 seconds when unleash.cache.ttl is not configured', function () {
    config()->set('unleash.cache', Arr::except(config('unleash.cache'), ['ttl']));

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'cacheTtl'))->toBe(15);
});

it('does not configure a stale cache handler when unleash.cache.stale_driver is not set', function () {
    config()->set('unleash.cache', Arr::except(config('unleash.cache'), ['stale_driver']));

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'staleCache'))->toBeNull();
});

it('does not configure a stale cache handler when unleash.cache.stale_driver is an empty string', function () {
    config()->set('unleash.cache.stale_driver', '');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'staleCache'))->toBeNull();
});

it('configures a stale cache handler for the configured store when unleash.cache.stale_driver is set', function () {
    config()->set('unleash.cache.stale_driver', 'array');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'staleCache'))->not->toBeNull();
});

it('passes the stale cache ttl to the client builder', function () {
    config()->set('unleash.cache.stale_ttl', 120);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'staleTtl'))->toBe(120);
});

it('defaults the stale cache ttl to 30 minutes when unleash.cache.stale_ttl is not configured', function () {
    config()->set('unleash.cache', Arr::except(config('unleash.cache'), ['stale_ttl']));

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'staleTtl'))->toBe(30 * 60);
});

it('does not enable development mode when unleash.development is false', function () {
    config()->set('unleash.development', false);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'fetchingEnabled'))->toBeTrue()
        ->and(builderProperty($builder, 'bootstrapProvider'))->toBeNull();
});

it('defaults to disabled development mode when unleash.development is not configured', function () {
    forgetUnleashConfig('development');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'fetchingEnabled'))->toBeTrue()
        ->and(builderProperty($builder, 'bootstrapProvider'))->toBeNull();
});

it('disables fetching and bootstraps from the configured file when unleash.development is true', function () {
    config()->set('unleash.development', true);
    config()->set('unleash.bootstrap_file', '/tmp/unleash-features.json');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'fetchingEnabled'))->toBeFalse()
        ->and(builderProperty($builder, 'bootstrapProvider'))->toBeInstanceOf(FileBootstrapProvider::class);
});

it('bootstraps from an empty provider when unleash.development is true without a bootstrap file', function () {
    config()->set('unleash.development', true);
    config()->set('unleash.bootstrap_file', null);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'fetchingEnabled'))->toBeFalse()
        ->and(builderProperty($builder, 'bootstrapProvider'))->toBeInstanceOf(EmptyBootstrapProvider::class);
});

it('keeps the builder\'s default strategies when unleash.strategies is not configured', function () {
    forgetUnleashConfig('strategies');

    $builder = buildUnleashClient();

    $strategies = builderProperty($builder, 'strategies');

    expect($strategies)->not->toBeEmpty()
        ->and($strategies[0])->toBeInstanceOf(DefaultStrategyHandler::class);
});

it('resolves the configured strategy classes and adds them to the builder', function () {
    config()->set('unleash.strategies', [CustomStrategyHandler::class]);

    $builder = buildUnleashClient();

    $strategies = builderProperty($builder, 'strategies');

    expect(array_filter($strategies, fn($strategy) => $strategy instanceof CustomStrategyHandler))
        ->toHaveCount(1)
        ->and($strategies[0])->toBeInstanceOf(DefaultStrategyHandler::class);
});

it('resolves configured strategy classes through the container', function () {
    config()->set('unleash.strategies', [DependentStrategyHandler::class]);

    $builder = buildUnleashClient();

    // DependentStrategyHandler requires a constructor-injected dependency
    // with no default, so `new $class()` would throw; this only succeeds
    // if the builder resolves it through the container.
    $strategies = builderProperty($builder, 'strategies');

    expect(end($strategies))->toEqual(app(DependentStrategyHandler::class));
});

it('does not register the unleash event dispatcher when unleash.events is not configured', function () {
    forgetUnleashConfig('events');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'eventSubscribers'))->toBeEmpty();
});

it('registers the unleash event dispatcher when unleash.events is true', function () {
    config()->set('unleash.events', true);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'eventSubscribers'))
        ->toHaveCount(1)
        ->and(builderProperty($builder, 'eventSubscribers')[0])->toBeInstanceOf(UnleashEventDispatcher::class);
});

it('does not register the unleash event dispatcher when unleash.events is false', function () {
    config()->set('unleash.events', false);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'eventSubscribers'))->toBeEmpty();
});

it('enables metrics with the default interval when unleash.metrics is not configured', function () {
    forgetUnleashConfig('metrics');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'metricsEnabled'))->toBeTrue()
        ->and(builderProperty($builder, 'metricsInterval'))->toBe(60_000)
        ->and(builderProperty($builder, 'metricsHandler'))->toBeNull();
});

it('passes the configured metrics enabled flag and interval to the client builder', function () {
    config()->set('unleash.metrics.enabled', false);
    config()->set('unleash.metrics.interval', 30_000);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'metricsEnabled'))->toBeFalse()
        ->and(builderProperty($builder, 'metricsInterval'))->toBe(30_000);
});

it('defaults to the sdk\'s own metrics handler when unleash.metrics.handler is not configured', function () {
    forgetUnleashConfig('metrics.handler');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'metricsHandler'))->toBeNull();
});

it('defaults to the sdk\'s own metrics handler when unleash.metrics.handler is set to DefaultMetricsHandler', function () {
    config()->set('unleash.metrics.handler', DefaultMetricsHandler::class);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'metricsHandler'))->toBeNull();
});

it('resolves the configured metrics handler class through the container', function () {
    config()->set('unleash.metrics.handler', CustomMetricsHandler::class);

    $builder = buildUnleashClient();

    // CustomMetricsHandler requires a constructor-injected dependency with
    // no default, so `new $class()` would throw; this only succeeds if the
    // builder resolves it through the container.
    expect(builderProperty($builder, 'metricsHandler'))->toEqual(app(CustomMetricsHandler::class));
});

it('defaults to the sdk\'s own variant handler when unleash.variant_handler is not configured', function () {
    forgetUnleashConfig('variant_handler');

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'variantHandler'))->toBeNull();
});

it('defaults to the sdk\'s own variant handler when unleash.variant_handler is set to DefaultVariantHandler', function () {
    config()->set('unleash.variant_handler', DefaultVariantHandler::class);

    $builder = buildUnleashClient();

    expect(builderProperty($builder, 'variantHandler'))->toBeNull();
});

it('resolves the configured variant handler class through the container', function () {
    config()->set('unleash.variant_handler', CustomVariantHandler::class);

    $builder = buildUnleashClient();

    // CustomVariantHandler requires a constructor-injected dependency with
    // no default, so `new $class()` would throw; this only succeeds if the
    // builder resolves it through the container.
    expect(builderProperty($builder, 'variantHandler'))->toEqual(app(CustomVariantHandler::class));
});

it('applies the customize callback before development mode overrides the builder', function () {
    config()->set('unleash.development', true);
    config()->set('unleash.bootstrap_file', null);

    $calls = 0;
    $builder = buildUnleashClient(function (UnleashBuilder $builder) use (&$calls) {
        $calls++;

        return $builder;
    });

    expect($calls)->toBe(1)
        ->and(builderProperty($builder, 'fetchingEnabled'))->toBeFalse();
});
