<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Variant;
use Unleash\Client\Variant\VariantHandler;

/**
 * Requires a constructor-injected dependency with no default, so it can
 * only be built through the container, not via a bare `new self()`.
 */
class CustomVariantHandler implements VariantHandler
{
    public function __construct(public readonly StrategyDependency $dependency)
    {
    }

    public function getDefaultVariant(): Variant
    {
        throw new \LogicException('not implemented');
    }

    public function selectVariant(array $variants, string $groupId, Context $context): ?Variant
    {
        throw new \LogicException('not implemented');
    }
}
