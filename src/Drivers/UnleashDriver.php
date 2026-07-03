<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Drivers;

use Closure;
use Henzeb\Pennant\Unleash\Configuration\UnleashContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Contracts\DefinesFeaturesExternally;
use Laravel\Pennant\Contracts\Driver;
use Unleash\Client\Configuration\Context;
use Unleash\Client\Enum\VariantPayloadType;
use Unleash\Client\Repository\UnleashRepository;
use Unleash\Client\Unleash;
use Unleash\Client\UnleashBuilder;

class UnleashDriver implements Driver, DefinesFeaturesExternally
{
    private ?Closure $contextResolver = null;
    private ?Closure $clientResolver = null;

    private ?Unleash $client = null;

    private ?UnleashRepository $repository = null;

    private ?UnleashBuilder $builder = null;

    /** @var array<string, callable> */
    private array $resolvers = [];

    public function __construct(private readonly UnleashBuilder $defaultBuilder)
    {
    }

    public function resolveUnleashContextUsing(?Closure $callback): static
    {
        $this->contextResolver = $callback;
        return $this;
    }

    public function buildUnleashClientUsing(?Closure $callback): static
    {
        $this->clientResolver = $callback;
        return $this;
    }

    private function builder(): UnleashBuilder
    {
        if ($this->builder !== null) {
            return $this->builder;
        }

        $builder = $this->defaultBuilder
            ->withAppUrl(config()->string('unleash.app_url', ''))
            ->withInstanceId(config()->string('unleash.instance_id', ''))
            ->withAppName(config()->string('unleash.app_name', ''))
            ->withHeader('Authorization', config()->string('unleash.api_key', ''))
            ->withCacheHandler(Cache::store(config()->string('unleash.cache_driver')));

        $builder = $this->getClientResolver()($builder);

        if (config()->boolean('unleash.development', false)) {
            $bootstrapFile = config('unleash.bootstrap_file');

            $builder = $builder
                ->withFetchingEnabled(false)
                ->withBootstrapFile(is_string($bootstrapFile) ? $bootstrapFile : null);
        }

        return $this->builder = $builder;
    }

    private function getClientResolver(): Closure
    {
        return $this->clientResolver ??= fn(UnleashBuilder $builder) => $builder;
    }

    private function repository(): UnleashRepository
    {
        if ($this->repository === null) {
            $this->repository = $this->builder()->buildRepository();
        }

        return $this->repository;
    }

    private function client(): Unleash
    {
        return $this->client ??= $this->builder()->withRepository($this->repository())->build();
    }

    public function define(string $feature, callable $resolver): void
    {
        $this->resolvers[$feature] = $resolver;
    }

    public function defined(): array
    {
        $features = [];
        foreach ($this->repository()->getFeatures() as $feature) {
            $features[] = $feature->getName();
        }

        return $features;
    }

    /**
     * Unleash toggles are not scoped by existence, only by evaluated state, so
     * every toggle is "defined" regardless of $scope. Whether a feature is
     * enabled for a given scope is determined separately by get().
     */
    public function definedFeaturesForScope(mixed $scope): array
    {
        return $this->defined();
    }

    public function getAll(array $features): array
    {
        $result = [];
        foreach ($features as $feature => $scopes) {
            $result[$feature] = array_map(
                fn(mixed $scope) => $this->get($feature, $scope),
                $scopes
            );
        }

        return $result;
    }

    public function getContextResolver(): Closure
    {
        return $this->contextResolver ??= fn(mixed $context) => $context;
    }
    private function resolveContext(mixed $scope): ?Context
    {
        $scope = $this->getContextResolver()($scope);

        if (is_string($scope)) {
            return UnleashContext::make(customContext: ['scope' => $scope]);
        }

        if($scope instanceof Authenticatable) {
            return UnleashContext::make(currentUserId: (string)$scope->getAuthIdentifier());
        }

        if($scope instanceof Model) {
            return UnleashContext::make(
                customContext: [
                    'class' => $scope->getMorphClass(),
                    'id' => (string) $scope->getKey()
                ]
            );
        }

        if ($scope instanceof Context) {
            return $scope;
        }

        return null;
    }

    public function get(string $feature, mixed $scope): mixed
    {
        if (isset($this->resolvers[$feature]) && !$this->repository()->findFeature($feature)) {
            return ($this->resolvers[$feature])($scope);
        }

        $variant = $this->client()->getVariant(
            $feature,
            $this->resolveContext($scope)
        );

        if (!$variant->isEnabled()) {
            return $variant->isFeatureEnabled();
        }

        $payload = $variant->getPayload();

        if ($payload === null) {
            return $variant->getName();
        }

        return match ($payload->getType()) {
            VariantPayloadType::JSON => $payload->fromJson(),
            VariantPayloadType::STRING,
            VariantPayloadType::CSV => $payload->getValue(),
        };
    }

    public function set(string $feature, mixed $scope, mixed $value): void
    {
    }

    public function setForAllScopes(string $feature, mixed $value): void
    {
    }

    public function delete(string $feature, mixed $scope): void
    {
    }

    public function purge(?array $features): void
    {
    }
}
