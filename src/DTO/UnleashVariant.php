<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\DTO;

use Illuminate\Support\Traits\Conditionable;
use Unleash\Client\DTO\DefaultVariantPayload;
use Unleash\Client\DTO\Variant;
use Unleash\Client\DTO\VariantOverride;
use Unleash\Client\DTO\VariantPayload;
use Unleash\Client\Enum\Stickiness;
use Unleash\Client\Enum\VariantPayloadType;

class UnleashVariant implements Variant
{
    use Conditionable;

    /**
     * @param array<VariantOverride> $overrides
     */
    public function __construct(
        private string $name,
        private bool $enabled = true,
        private int $weight = 0,
        private string $stickiness = Stickiness::DEFAULT,
        private ?VariantPayload $payload = null,
        private array $overrides = [],
        private bool $featureEnabled = true,
    ) {
    }

    /**
     * @param array<VariantOverride> $overrides
     */
    public static function make(
        string $name,
        mixed $payload = null,
        bool $enabled = true,
        int $weight = 0,
        string $stickiness = Stickiness::DEFAULT,
        array $overrides = [],
        bool $featureEnabled = true,
    ): self {
        $variant = new self(
            name: $name,
            enabled: $enabled,
            weight: $weight,
            stickiness: $stickiness,
            overrides: $overrides,
            featureEnabled: $featureEnabled,
        );

        if ($payload !== null) {
            $variant->payload($payload);
        }

        return $variant;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function featureEnabled(bool $featureEnabled = true): self
    {
        $this->featureEnabled = $featureEnabled;

        return $this;
    }

    public function weight(int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function stickiness(string $stickiness): self
    {
        $this->stickiness = $stickiness;

        return $this;
    }

    /**
     * @param array<VariantOverride> $overrides
     */
    public function overrides(array $overrides): self
    {
        $this->overrides = $overrides;

        return $this;
    }

    /**
     * Wraps the value in a payload, picking the type based on its shape:
     * arrays become JSON payloads, strings become plain string payloads.
     */
    public function payload(array|string $value): self
    {
        $this->payload = is_array($value)
            ? new DefaultVariantPayload(VariantPayloadType::JSON, json_encode($value))
            : new DefaultVariantPayload(VariantPayloadType::STRING, $value);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPayload(): ?VariantPayload
    {
        return $this->payload;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * @return array<VariantOverride>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    public function getStickiness(): string
    {
        return $this->stickiness;
    }

    public function isFeatureEnabled(): bool
    {
        return $this->featureEnabled;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'name' => $this->name,
            'enabled' => $this->enabled,
            'feature_enabled' => $this->featureEnabled,
        ];

        if ($this->payload !== null) {
            $result['payload'] = $this->payload->jsonSerialize();
        }

        return $result;
    }
}
