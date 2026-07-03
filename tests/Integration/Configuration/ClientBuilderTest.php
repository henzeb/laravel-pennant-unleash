<?php

use Henzeb\Pennant\Unleash\Drivers\UnleashDriver;
use Laravel\Pennant\Feature;
use Unleash\Client\UnleashBuilder;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();
});

afterEach(function () {
    $this->deleteUnleashFeature($this->featureName);
});

it('uses a custom client builder callback and reuses the built client across calls', function () {
    $calls = 0;

    Feature::buildUnleashClientUsing(function (UnleashBuilder $builder) use (&$calls) {
        $calls++;

        return $builder;
    });

    $this->createUnleashFeature($this->featureName, enabled: true);

    expect(Feature::active($this->featureName))->toBeTrue()
        ->and($calls)->toBe(1);
});
