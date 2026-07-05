<?php

use Laravel\Pennant\Feature;
use Tests\Fixtures\CustomStrategyHandler;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'custom-strategy-feature-' . uniqid();
    $this->strategyName = 'custom-' . uniqid();

    app()->bind(CustomStrategyHandler::class, fn() => new CustomStrategyHandler($this->strategyName));

    $this->createCustomStrategyFeature($this->featureName, $this->strategyName);
});

afterEach(function () {
    $this->deleteUnleashFeature($this->featureName);
    $this->deleteUnleashStrategy($this->strategyName);
});

it('ignores a feature strategy unleash has no handler for', function () {
    expect(Feature::active($this->featureName))->toBeFalse();
});

it('evaluates a feature using a custom strategy resolved from the strategies config', function () {
    config()->set('unleash.strategies', [CustomStrategyHandler::class]);

    expect(Feature::active($this->featureName))->toBeTrue();
});
