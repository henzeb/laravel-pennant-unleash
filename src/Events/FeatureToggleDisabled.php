<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Events;

use Unleash\Client\Configuration\Context;
use Unleash\Client\DTO\Feature;

class FeatureToggleDisabled
{
    public function __construct(
        public readonly Feature $feature,
        public readonly Context $context,
    ) {
    }
}
