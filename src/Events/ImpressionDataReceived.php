<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Events;

class ImpressionDataReceived
{
    /**
     * @param array{
     *     currentTime: \DateTimeInterface,
     *     userId: string|null,
     *     sessionId: string|null,
     *     remoteAddress: string|null,
     *     environment: string|null,
     *     appName: string,
     *     properties: array<string, string>
     * } $context
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $eventId,
        public readonly array $context,
        public readonly bool $enabled,
        public readonly string $featureName,
        public readonly ?string $variant,
    ) {
    }
}
