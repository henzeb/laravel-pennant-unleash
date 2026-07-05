<?php

namespace Tests;

use Henzeb\Pennant\Unleash\Providers\PennantUnleashServiceProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PennantServiceProvider::class,
            PennantUnleashServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('pennant.stores.unleash', ['driver' => 'unleash']);
        $app['config']->set('unleash.cache.ttl', 0);
    }

    private function unleashAdmin(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['Authorization' => env('UNLEASH_ADMIN_TOKEN')])
            ->baseUrl(env('UNLEASH_ADMIN_URL'))
            ->retry(25, 400, when: fn (\Throwable $e) => $e instanceof ConnectionException, throw: false);
    }

    protected function createUnleashFeature(string $name, bool $enabled = false): void
    {
        $this->unleashAdmin()->post('/projects/default/features', ['name' => $name, 'type' => 'release']);

        if ($enabled) {
            $this->enableUnleashFeature($name);
            return;
        }

        $this->waitForUnleashClientApi(
            fn(array $features) => collect($features)->contains(fn(array $feature) => $feature['name'] === $name)
        );
    }

    protected function enableUnleashFeature(string $name, string $environment = 'development'): void
    {
        $this->unleashAdmin()->post("/projects/default/features/{$name}/environments/{$environment}/on", ['enabled' => true]);
        $this->waitForUnleashClientApi(
            fn(array $features) => collect($features)->firstWhere('name', $name)['enabled'] ?? false
        );
    }

    protected function disableUnleashFeature(string $name, string $environment = 'development'): void
    {
        $this->unleashAdmin()->post("/projects/default/features/{$name}/environments/{$environment}/off", ['enabled' => false]);
        $this->waitForUnleashClientApi(
            fn(array $features) => !(collect($features)->firstWhere('name', $name)['enabled'] ?? true)
        );
    }

    protected function addUnleashVariant(string $featureName, string $variantName, ?array $payload = null, string $environment = 'development'): void
    {
        $strategies = $this->unleashAdmin()
            ->get("/projects/default/features/{$featureName}/environments/{$environment}/strategies")
            ->json();
        $strategyId = $strategies[0]['id'] ?? null;

        $existing = collect($strategies[0]['variants'] ?? [])
            ->map(fn($v) => array_intersect_key($v, array_flip(['name', 'weight', 'weightType', 'stickiness', 'payload'])))
            ->all();

        $variant = ['name' => $variantName, 'weight' => 1000, 'weightType' => 'variable', 'stickiness' => 'default'];

        if ($payload !== null) {
            $variant['payload'] = $payload;
        }

        $this->unleashAdmin()->patch(
            "/projects/default/features/{$featureName}/environments/{$environment}/strategies/{$strategyId}",
            [['op' => 'replace', 'path' => '/variants', 'value' => [...$existing, $variant]]]
        );

        $this->waitForUnleashClientApi(fn(array $features) => collect($features)
            ->firstWhere('name', $featureName)['strategies'][0]['variants'][0]['name'] ?? null === $variantName
        );
    }

    protected function createCustomStrategyFeature(string $name, string $strategyName): void
    {
        $this->unleashAdmin()->post('/strategies', [
            'name' => $strategyName,
            'description' => 'created for tests',
            'parameters' => [],
        ]);

        $this->unleashAdmin()->post('/projects/default/features', ['name' => $name, 'type' => 'release']);

        $this->unleashAdmin()->post(
            "/projects/default/features/{$name}/environments/development/strategies",
            [
                'name' => $strategyName,
                'parameters' => (object) [],
                'constraints' => [],
                'segments' => [],
            ]
        );

        $this->enableUnleashFeature($name);
        $this->waitForUnleashClientApi(
            fn(array $features) => (collect($features)->firstWhere('name', $name)['strategies'][0]['name'] ?? null) === $strategyName
        );
    }

    protected function deleteUnleashStrategy(string $strategyName): void
    {
        $this->unleashAdmin()->delete("/strategies/{$strategyName}");
    }

    protected function createUserWithIdFeature(string $name, string ...$userIds): void
    {
        $this->unleashAdmin()->post('/projects/default/features', ['name' => $name, 'type' => 'release']);

        $this->unleashAdmin()->post(
            "/projects/default/features/{$name}/environments/development/strategies",
            [
                'name' => 'default',
                'parameters' => (object) [],
                'constraints' => [['contextName' => 'userId', 'operator' => 'IN', 'values' => $userIds]],
                'segments' => [],
            ]
        );

        $this->enableUnleashFeature($name);
        $this->waitForStrategyConstraints($name, [['contextName' => 'userId', 'values' => $userIds]]);
    }

    protected function createStringScopeFeature(string $name, string $scope): void
    {
        $this->unleashAdmin()->post('/projects/default/features', ['name' => $name, 'type' => 'release']);

        $this->unleashAdmin()->post(
            "/projects/default/features/{$name}/environments/development/strategies",
            [
                'name' => 'default',
                'parameters' => (object) [],
                'constraints' => [['contextName' => 'scope', 'operator' => 'IN', 'values' => [$scope]]],
                'segments' => [],
            ]
        );

        $this->enableUnleashFeature($name);
        $this->waitForStrategyConstraints($name, [['contextName' => 'scope', 'values' => [$scope]]]);
    }

    protected function createArrayScopeFeature(string $name, string $contextName, string $value): void
    {
        $this->unleashAdmin()->post('/projects/default/features', ['name' => $name, 'type' => 'release']);

        $this->unleashAdmin()->post(
            "/projects/default/features/{$name}/environments/development/strategies",
            [
                'name' => 'default',
                'parameters' => (object) [],
                'constraints' => [['contextName' => $contextName, 'operator' => 'IN', 'values' => [$value]]],
                'segments' => [],
            ]
        );

        $this->enableUnleashFeature($name);
        $this->waitForStrategyConstraints($name, [['contextName' => $contextName, 'values' => [$value]]]);
    }

    protected function createModelScopeFeature(string $name, string $class, string $id): void
    {
        $this->unleashAdmin()->post('/projects/default/features', ['name' => $name, 'type' => 'release']);

        $this->unleashAdmin()->post(
            "/projects/default/features/{$name}/environments/development/strategies",
            [
                'name' => 'default',
                'parameters' => (object) [],
                'constraints' => [
                    ['contextName' => 'model', 'operator' => 'IN', 'values' => [$class]],
                    ['contextName' => 'key', 'operator' => 'IN', 'values' => [$id]],
                ],
                'segments' => [],
            ]
        );

        $this->enableUnleashFeature($name);
        $this->waitForStrategyConstraints($name, [
            ['contextName' => 'model', 'values' => [$class]],
            ['contextName' => 'key', 'values' => [$id]],
        ]);
    }

    /**
     * Waits until the feature's first strategy reports the given constraints, since
     * enabling a feature does not guarantee its strategy constraints have propagated yet.
     *
     * @param array<int, array{contextName: string, values: array<string>}> $expectedConstraints
     */
    private function waitForStrategyConstraints(string $name, array $expectedConstraints): void
    {
        $this->waitForUnleashClientApi(function (array $features) use ($name, $expectedConstraints) {
            $constraints = collect($features)->firstWhere('name', $name)['strategies'][0]['constraints'] ?? [];

            foreach ($expectedConstraints as $expected) {
                $match = collect($constraints)->first(fn(array $constraint) => $constraint['contextName'] === $expected['contextName']);

                if (($match['values'] ?? null) !== $expected['values']) {
                    return false;
                }
            }

            return true;
        });
    }

    protected function deleteUnleashFeature(string $name): void
    {
        $this->unleashAdmin()->delete("/projects/default/features/{$name}");
        $this->unleashAdmin()->delete("/archive/{$name}");
    }

    private function waitForUnleashClientApi(callable $condition, int $timeoutMs = 15000): void
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        do {
            $features = $this->fetchUnleashClientFeatures();
            if ($features !== null && $condition($features)) {
                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);
    }

    /**
     * Returns null (instead of letting the exception surface) on a dropped connection,
     * so a transient blip just counts as "not matching yet" to the caller's poll loop
     * instead of hard-failing the test.
     */
    private function fetchUnleashClientFeatures(): ?array
    {
        try {
            return Http::withHeaders(['Authorization' => env('UNLEASH_API_KEY')])
                ->retry(5, 200, throw: false)
                ->get(env('UNLEASH_URL') . '/client/features')
                ->json('features') ?? [];
        } catch (ConnectionException) {
            return null;
        }
    }
}
