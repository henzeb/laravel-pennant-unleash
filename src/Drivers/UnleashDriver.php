<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Drivers;

use Closure;
use Henzeb\Pennant\Unleash\Configuration\UnleashClientBuilder;
use Henzeb\Pennant\Unleash\Configuration\UnleashContext;
use Henzeb\Pennant\Unleash\DTO\UnleashVariant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Contracts\DefinesFeaturesExternally;
use Laravel\Pennant\Contracts\Driver;
use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\DefaultFeature;
use Unleash\Client\DTO\Feature;
use Unleash\Client\DTO\Variant;
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
        return $this->builder ??= (new UnleashClientBuilder())->build($this->defaultBuilder, $this->getClientResolver());
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

        if ($scope instanceof Authenticatable) {
            return UnleashContext::make(currentUserId: (string)$scope->getAuthIdentifier());
        }

        if ($scope instanceof Model) {
            return UnleashContext::make(
                customContext: [
                    'model' => $scope->getMorphClass(),
                    'key' => (string)$scope->getKey()
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
        $featureName = $feature;
        $feature = $this->repository()->findFeature($feature);

        if (!$feature) {
            if (isset($this->resolvers[$featureName])) {
                return $this->resolveDefault($featureName, $scope);
            }

            return $this->client()->isEnabled(
                $featureName,
                $this->resolveContext($scope)
            );
        }

        if (!$this->hasVariants($feature)) {
            $default = $this->resolveDefault($featureName, $scope);

            return $this->client()->isEnabled(
                $featureName,
                $this->resolveContext($scope),
                is_bool($default) ? $default : false
            );
        }

        $variant = $this->client()->getVariant(
            $featureName,
            $this->resolveContext($scope),
            $this->resolveVariantDefault($featureName, $scope)
        );

        if (!$variant->isEnabled()) {
            return false;
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

    private function resolveDefault(string $feature, mixed $scope): mixed
    {
        return ($this->resolvers[$feature] ?? fn() => false)($scope);
    }

    private function hasVariants(Feature $feature): bool
    {
        if (count($feature->getVariants())) {
            return true;
        }

        foreach ($feature->getStrategies() as $strategy) {
            if (count($strategy->getVariants())) {
                return true;
            }
        }

        return false;
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

    /**
     * @param array<string>|null $features
     * @return void
     */
    public function purge(?array $features): void
    {
    }

    private function resolveVariantDefault(string $featureName, mixed $scope): ?Variant
    {
        $default = $this->resolveDefault($featureName, $scope);

        if (!$default) {
            return null;
        }

        if ($default instanceof Variant) {
            return $default;
        }

        return UnleashVariant::make('default', $default);
    }
}
