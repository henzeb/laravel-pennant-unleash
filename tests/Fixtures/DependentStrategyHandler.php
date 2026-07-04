<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Strategy;
use Unleash\Client\Strategy\StrategyHandler;

/**
 * Requires a constructor-injected dependency with no default, so it can
 * only be built through the container, not via a bare `new self()`.
 */
class DependentStrategyHandler implements StrategyHandler
{
    public function __construct(public readonly StrategyDependency $dependency)
    {
    }

    public function supports(Strategy $strategy): bool
    {
        return $strategy->getName() === 'dependent';
    }

    public function getStrategyName(): string
    {
        return 'dependent';
    }

    public function isEnabled(Strategy $strategy, Context $context): bool
    {
        return true;
    }
}
