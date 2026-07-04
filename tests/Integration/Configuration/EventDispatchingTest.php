<?php

use Henzeb\Pennant\Unleash\Events\FeatureToggleNotFound;
use Illuminate\Support\Facades\Event;
use Laravel\Pennant\Feature;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();
});

it('does not dispatch unleash events when unleash.events is not configured', function () {
    Event::fake();

    Feature::active($this->featureName);

    Event::assertNotDispatched(FeatureToggleNotFound::class);
});

it('dispatches a FeatureToggleNotFound laravel event when the feature does not exist in unleash and unleash.events is enabled', function () {
    config()->set('unleash.events', true);
    Event::fake();

    Feature::active($this->featureName);

    Event::assertDispatched(
        FeatureToggleNotFound::class,
        fn(FeatureToggleNotFound $event) => $event->featureName === $this->featureName
    );
});
