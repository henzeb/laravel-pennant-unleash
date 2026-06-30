<?php

use Laravel\Pennant\Feature;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();
});

afterEach(function () {
    $this->deleteUnleashFeature($this->featureName . '-enabled');
    $this->deleteUnleashFeature($this->featureName . '-disabled');
});

it('returns all features from unleash with their values', function () {
    $this->createUnleashFeature($this->featureName . '-enabled', enabled: true);
    $this->createUnleashFeature($this->featureName . '-disabled', enabled: false);

    $features = Feature::for(null)->all();

    expect($features)
        ->toHaveKey($this->featureName . '-enabled', true)
        ->toHaveKey($this->featureName . '-disabled', false);
});
