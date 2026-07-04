<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Unleash\Client\DTO\Feature;
use Unleash\Client\DTO\Variant;
use Unleash\Client\Metrics\MetricsHandler;

/**
 * Requires a constructor-injected dependency with no default, so it can
 * only be built through the container, not via a bare `new self()`.
 */
class CustomMetricsHandler implements MetricsHandler
{
    public function __construct(public readonly StrategyDependency $dependency)
    {
    }

    public function handleMetrics(Feature $feature, bool $successful, ?Variant $variant = null): void
    {
    }
}
