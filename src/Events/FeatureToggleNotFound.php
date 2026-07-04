<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Events;

use Unleash\Client\Configuration\Context;

class FeatureToggleNotFound
{
    public function __construct(
        public readonly Context $context,
        public readonly string $featureName,
    ) {
    }
}
