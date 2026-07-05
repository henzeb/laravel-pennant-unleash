<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Strategy;
use Unleash\Client\Strategy\StrategyHandler;

class CustomStrategyHandler implements StrategyHandler
{
    public function __construct(private readonly string $strategyName = 'custom')
    {
    }

    public function supports(Strategy $strategy): bool
    {
        return $strategy->getName() === $this->strategyName;
    }

    public function getStrategyName(): string
    {
        return $this->strategyName;
    }

    public function isEnabled(Strategy $strategy, Context $context): bool
    {
        return true;
    }
}
