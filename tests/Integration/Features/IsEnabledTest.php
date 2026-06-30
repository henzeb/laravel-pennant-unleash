<?php

use Laravel\Pennant\Feature;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();
});

afterEach(function () {
    $this->deleteUnleashFeature($this->featureName);
});

it('returns true when feature flag is enabled', function () {
    $this->createUnleashFeature($this->featureName, enabled: true);

    expect(Feature::active($this->featureName))->toBeTrue();
});

it('returns false when feature flag is disabled', function () {
    $this->createUnleashFeature($this->featureName);

    expect(Feature::active($this->featureName))->toBeFalse();
});

it('returns false when feature flag does not exist', function () {
    expect(Feature::active($this->featureName))->toBeFalse();
});
