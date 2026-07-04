<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Strategy;
use Unleash\Client\Strategy\StrategyHandler;

class CustomStrategyHandler implements StrategyHandler
{
    public function supports(Strategy $strategy): bool
    {
        return $strategy->getName() === 'custom';
    }

    public function getStrategyName(): string
    {
        return 'custom';
    }

    public function isEnabled(Strategy $strategy, Context $context): bool
    {
        return true;
    }
}
