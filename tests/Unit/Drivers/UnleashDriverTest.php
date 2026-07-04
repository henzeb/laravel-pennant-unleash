<?php

use Henzeb\Pennant\Unleash\Drivers\UnleashDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery\MockInterface;
use Tests\Fixtures\ScopedModel;
use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\DefaultFeature;
use Unleash\Client\DTO\DefaultVariant;
use Unleash\Client\DTO\DefaultVariantPayload;
use Unleash\Client\Repository\UnleashRepository;
use Unleash\Client\Unleash;
use Unleash\Client\UnleashBuilder;

covers(UnleashDriver::class);

afterEach(function () {
    Mockery::close();
});

/**
 * Builds a driver with its lazily-built client and repository replaced by
 * mocks, so the driver's own logic can be exercised without ever reaching
 * the network.
 */
function driverWithMocks(UnleashRepository&MockInterface $repository, Unleash&MockInterface $client): UnleashDriver
{
    $driver = new UnleashDriver(UnleashBuilder::create());

    $reflection = new ReflectionClass($driver);

    $repositoryProperty = $reflection->getProperty('repository');
    $repositoryProperty->setAccessible(true);
    $repositoryProperty->setValue($driver, $repository);

    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($driver, $client);

    return $driver;
}

it('uses the locally defined resolver when the feature is not found in unleash', function () {
    $repository = Mockery::mock(UnleashRepository::class);
    $repository->shouldReceive('findFeature')->once()->with('my-feature')->andReturnNull();

    $client = Mockery::mock(Unleash::class);
    $client->shouldNotReceive('getVariant');

    $driver = driverWithMocks($repository, $client);
    $driver->define('my-feature', fn(mixed $scope) => 'local-default');

    expect($driver->get('my-feature', null))->toBe('local-default');
});

it('ignores the locally defined resolver once the feature exists in unleash', function () {
    $repository = Mockery::mock(UnleashRepository::class);
    $repository->shouldReceive('findFeature')->once()->with('my-feature')
        ->andReturn(new DefaultFeature('my-feature', true, []));

    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()
        ->andReturn(new DefaultVariant('disabled', false, featureEnabled: true));

    $driver = driverWithMocks($repository, $client);
    $driver->define('my-feature', fn(mixed $scope) => 'local-default');

    expect($driver->get('my-feature', null))->toBeTrue();
});

it('does not consult the repository when no local resolver is defined', function () {
    $repository = Mockery::mock(UnleashRepository::class);
    $repository->shouldNotReceive('findFeature');

    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()
        ->andReturn(new DefaultVariant('disabled', false, featureEnabled: false));

    $driver = driverWithMocks($repository, $client);

    expect($driver->get('my-feature', null))->toBeFalse();
});

it('returns whether the feature is enabled when the variant itself is disabled', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()
        ->andReturn(new DefaultVariant('disabled', false, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    expect($driver->get('my-feature', null))->toBeTrue();
});

it('returns the variant name when the variant is enabled without a payload', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()
        ->andReturn(new DefaultVariant('my-variant', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    expect($driver->get('my-feature', null))->toBe('my-variant');
});

it('returns the string payload value', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->andReturn(new DefaultVariant(
        'my-variant', true, payload: new DefaultVariantPayload('string', 'hello'), featureEnabled: true,
    ));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    expect($driver->get('my-feature', null))->toBe('hello');
});

it('returns the csv payload value', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->andReturn(new DefaultVariant(
        'my-variant', true, payload: new DefaultVariantPayload('csv', 'a,b,c'), featureEnabled: true,
    ));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    expect($driver->get('my-feature', null))->toBe('a,b,c');
});

it('returns the decoded json payload', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->andReturn(new DefaultVariant(
        'my-variant', true, payload: new DefaultVariantPayload('json', '{"foo":"bar"}'), featureEnabled: true,
    ));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    expect($driver->get('my-feature', null))->toBe(['foo' => 'bar']);
});

it('lists the feature names known to the repository', function () {
    $repository = Mockery::mock(UnleashRepository::class);
    $repository->shouldReceive('getFeatures')->once()->andReturn([
        new DefaultFeature('feature-a', true, []),
        new DefaultFeature('feature-b', false, []),
    ]);

    $driver = driverWithMocks($repository, Mockery::mock(Unleash::class));

    expect($driver->defined())->toBe(['feature-a', 'feature-b']);
});

it('returns the same defined features regardless of scope', function () {
    $repository = Mockery::mock(UnleashRepository::class);
    $repository->shouldReceive('getFeatures')->once()->andReturn([
        new DefaultFeature('feature-a', true, []),
    ]);

    $driver = driverWithMocks($repository, Mockery::mock(Unleash::class));

    expect($driver->definedFeaturesForScope('any-scope'))->toBe(['feature-a']);
});

it('resolves the value for every feature and scope combination', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')
        ->once()->with('feature-a', Mockery::any())
        ->andReturn(new DefaultVariant('a', true, featureEnabled: true));
    $client->shouldReceive('getVariant')
        ->once()->with('feature-b', Mockery::any())
        ->andReturn(new DefaultVariant('disabled', false, featureEnabled: false));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    expect($driver->getAll(['feature-a' => [null], 'feature-b' => [null]]))->toBe([
        'feature-a' => ['a'],
        'feature-b' => [false],
    ]);
});

it('resolves a string scope to a context using a custom context property', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->with(
        'my-feature',
        Mockery::on(fn(Context $context) => $context->getCustomProperty('scope') === 'my-scope'),
    )->andReturn(new DefaultVariant('a', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    $driver->get('my-feature', 'my-scope');
});

it('resolves an authenticatable scope to a context using the current user id', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(42);

    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->with(
        'my-feature',
        Mockery::on(fn(Context $context) => $context->getCurrentUserId() === '42'),
    )->andReturn(new DefaultVariant('a', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    $driver->get('my-feature', $user);
});

it('resolves an eloquent model scope to a context using its class and key', function () {
    $model = (new ScopedModel())->forceFill(['id' => 7]);

    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->with(
        'my-feature',
        Mockery::on(fn(Context $context) => $context->getCustomProperty('class') === ScopedModel::class
            && $context->getCustomProperty('id') === '7'),
    )->andReturn(new DefaultVariant('a', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    $driver->get('my-feature', $model);
});

it('passes an existing context through unchanged', function () {
    $context = Mockery::mock(Context::class);

    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->with('my-feature', $context)
        ->andReturn(new DefaultVariant('a', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    $driver->get('my-feature', $context);
});

it('resolves an unsupported scope type to a null context', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->with('my-feature', null)
        ->andReturn(new DefaultVariant('a', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);

    $driver->get('my-feature', 42);
});

it('applies a custom context resolver before converting the scope to a context', function () {
    $client = Mockery::mock(Unleash::class);
    $client->shouldReceive('getVariant')->once()->with(
        'my-feature',
        Mockery::on(fn(Context $context) => $context->getCustomProperty('scope') === 'transformed'),
    )->andReturn(new DefaultVariant('a', true, featureEnabled: true));

    $driver = driverWithMocks(Mockery::mock(UnleashRepository::class), $client);
    $driver->resolveUnleashContextUsing(fn(mixed $scope) => 'transformed');

    $driver->get('my-feature', 'original-scope');
});

it('defaults the context resolver to returning the scope unchanged', function () {
    $driver = new UnleashDriver(UnleashBuilder::create());

    expect($driver->getContextResolver()('untouched'))->toBe('untouched');
});

it('resets the context resolver to the default when null is passed', function () {
    $driver = new UnleashDriver(UnleashBuilder::create());

    $driver->resolveUnleashContextUsing(fn(mixed $scope) => 'transformed');
    $driver->resolveUnleashContextUsing(null);

    expect($driver->getContextResolver()('untouched'))->toBe('untouched');
});

it('builds the client only once, applying the custom client builder callback', function () {
    $calls = 0;

    $driver = new UnleashDriver(UnleashBuilder::create());
    $driver->buildUnleashClientUsing(function (UnleashBuilder $builder) use (&$calls) {
        $calls++;

        return $builder;
    });

    $reflection = new ReflectionClass($driver);
    $builderMethod = $reflection->getMethod('builder');
    $builderMethod->setAccessible(true);

    $first = $builderMethod->invoke($driver);
    $second = $builderMethod->invoke($driver);

    expect($first)->toBe($second)
        ->and($calls)->toBe(1);
});

it('does not mutate state for the unsupported write operations', function () {
    $driver = new UnleashDriver(UnleashBuilder::create());

    $driver->set('my-feature', null, 'value');
    $driver->setForAllScopes('my-feature', 'value');
    $driver->delete('my-feature', null);
    $driver->purge(['my-feature']);
})->throwsNoExceptions();
