<?php

use Henzeb\Pennant\Unleash\DTO\UnleashVariant;
use Unleash\Client\DTO\DefaultVariantOverride;
use Unleash\Client\Enum\Stickiness;
use Unleash\Client\Enum\VariantPayloadType;

covers(UnleashVariant::class);

it('constructs with sensible defaults', function () {
    $variant = new UnleashVariant('my-variant');

    expect($variant->getName())->toBe('my-variant')
        ->and($variant->isEnabled())->toBeTrue()
        ->and($variant->getWeight())->toBe(0)
        ->and($variant->getStickiness())->toBe(Stickiness::DEFAULT)
        ->and($variant->getPayload())->toBeNull()
        ->and($variant->getOverrides())->toBe([])
        ->and($variant->isFeatureEnabled())->toBeTrue();
});

it('accepts all values through the constructor', function () {
    $override = new DefaultVariantOverride('field', ['value']);

    $variant = new UnleashVariant(
        name: 'my-variant',
        enabled: false,
        weight: 100,
        stickiness: 'userId',
        payload: null,
        overrides: [$override],
        featureEnabled: false,
    );

    expect($variant->getName())->toBe('my-variant')
        ->and($variant->isEnabled())->toBeFalse()
        ->and($variant->getWeight())->toBe(100)
        ->and($variant->getStickiness())->toBe('userId')
        ->and($variant->getOverrides())->toBe([$override])
        ->and($variant->isFeatureEnabled())->toBeFalse();
});

it('makes a variant with defaults when only a name is given', function () {
    $variant = UnleashVariant::make('my-variant');

    expect($variant->getName())->toBe('my-variant')
        ->and($variant->isEnabled())->toBeTrue()
        ->and($variant->getWeight())->toBe(0)
        ->and($variant->getStickiness())->toBe(Stickiness::DEFAULT)
        ->and($variant->getPayload())->toBeNull()
        ->and($variant->getOverrides())->toBe([])
        ->and($variant->isFeatureEnabled())->toBeTrue();
});

it('makes a variant with all values passed at once', function () {
    $override = new DefaultVariantOverride('field', ['value']);

    $variant = UnleashVariant::make(
        name: 'my-variant',
        payload: 'hello',
        enabled: false,
        weight: 50,
        stickiness: 'sessionId',
        overrides: [$override],
        featureEnabled: false,
    );

    expect($variant->getName())->toBe('my-variant')
        ->and($variant->isEnabled())->toBeFalse()
        ->and($variant->getWeight())->toBe(50)
        ->and($variant->getStickiness())->toBe('sessionId')
        ->and($variant->getOverrides())->toBe([$override])
        ->and($variant->isFeatureEnabled())->toBeFalse()
        ->and($variant->getPayload()->getType())->toBe(VariantPayloadType::STRING)
        ->and($variant->getPayload()->getValue())->toBe('hello');
});

it('sets the name fluently', function () {
    $variant = (new UnleashVariant('original'))->name('renamed');

    expect($variant->getName())->toBe('renamed');
});

it('sets enabled fluently, defaulting to true', function () {
    $variant = new UnleashVariant('my-variant', enabled: false);

    expect($variant->enabled()->isEnabled())->toBeTrue()
        ->and($variant->enabled(false)->isEnabled())->toBeFalse();
});

it('sets feature enabled fluently, defaulting to true', function () {
    $variant = new UnleashVariant('my-variant', featureEnabled: false);

    expect($variant->featureEnabled()->isFeatureEnabled())->toBeTrue()
        ->and($variant->featureEnabled(false)->isFeatureEnabled())->toBeFalse();
});

it('sets the weight fluently', function () {
    $variant = (new UnleashVariant('my-variant'))->weight(42);

    expect($variant->getWeight())->toBe(42);
});

it('sets the stickiness fluently', function () {
    $variant = (new UnleashVariant('my-variant'))->stickiness('userId');

    expect($variant->getStickiness())->toBe('userId');
});

it('sets the overrides fluently', function () {
    $override = new DefaultVariantOverride('field', ['value']);

    $variant = (new UnleashVariant('my-variant'))->overrides([$override]);

    expect($variant->getOverrides())->toBe([$override]);
});

it('wraps a string value in a string payload', function () {
    $variant = (new UnleashVariant('my-variant'))->payload('hello');

    expect($variant->getPayload()->getType())->toBe(VariantPayloadType::STRING)
        ->and($variant->getPayload()->getValue())->toBe('hello');
});

it('wraps an array value in a json payload', function () {
    $variant = (new UnleashVariant('my-variant'))->payload(['foo' => 'bar']);

    expect($variant->getPayload()->getType())->toBe(VariantPayloadType::JSON)
        ->and($variant->getPayload()->fromJson())->toBe(['foo' => 'bar']);
});

it('serializes to json without a payload', function () {
    $variant = new UnleashVariant('my-variant', enabled: true, featureEnabled: false);

    expect($variant->jsonSerialize())->toBe([
        'name' => 'my-variant',
        'enabled' => true,
        'feature_enabled' => false,
    ]);
});

it('serializes to json with a payload', function () {
    $variant = (new UnleashVariant('my-variant'))->payload(['foo' => 'bar']);

    expect($variant->jsonSerialize())->toBe([
        'name' => 'my-variant',
        'enabled' => true,
        'feature_enabled' => true,
        'payload' => [
            'type' => VariantPayloadType::JSON,
            'value' => json_encode(['foo' => 'bar']),
        ],
    ]);
});

it('applies the callback when a conditionable condition is true', function () {
    $variant = UnleashVariant::make('trial')
        ->when(true, fn(UnleashVariant $variant) => $variant->payload(['plan' => 'enterprise']));

    expect($variant->getPayload()->fromJson())->toBe(['plan' => 'enterprise']);
});

it('does not apply the callback when a conditionable condition is false', function () {
    $variant = UnleashVariant::make('trial')
        ->when(false, fn(UnleashVariant $variant) => $variant->payload(['plan' => 'enterprise']));

    expect($variant->getPayload())->toBeNull();
});

it('applies the callback when a conditionable unless condition is false', function () {
    $variant = UnleashVariant::make('trial')
        ->unless(false, fn(UnleashVariant $variant) => $variant->payload(['plan' => 'enterprise']));

    expect($variant->getPayload()->fromJson())->toBe(['plan' => 'enterprise']);
});
