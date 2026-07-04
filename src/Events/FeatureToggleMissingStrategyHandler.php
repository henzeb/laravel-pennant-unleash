<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Events;

use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Feature;

class FeatureToggleMissingStrategyHandler
{
    public function __construct(
        public readonly Context $context,
        public readonly Feature $feature,
    ) {
    }
}
