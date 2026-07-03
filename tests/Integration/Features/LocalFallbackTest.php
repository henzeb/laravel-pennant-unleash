<?php

use Henzeb\Pennant\Unleash\Drivers\UnleashDriver;
use Laravel\Pennant\Feature;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();
});

it('uses the locally defined resolver when the feature does not exist in unleash', function () {
    Feature::define($this->featureName, fn (mixed $scope) => 'local-default');

    expect(Feature::value($this->featureName))->toBe('local-default');
});

it('ignores the locally defined resolver once the feature exists in unleash', function () {
    Feature::define($this->featureName, fn (mixed $scope) => 'local-default');

    $this->createUnleashFeature($this->featureName, enabled: true);

    expect(Feature::active($this->featureName))->toBeTrue();

    $this->deleteUnleashFeature($this->featureName);
});

it('excludes a locally defined resolver from defined() when it does not exist in unleash', function () {
    Feature::define($this->featureName, fn (mixed $scope) => 'local-default');

    /** @var UnleashDriver $driver */
    $driver = Feature::getDriver();

    expect($driver->defined())->not->toContain($this->featureName);
});

it('includes a feature in defined() once it exists in unleash, regardless of a local resolver', function () {
    Feature::define($this->featureName, fn (mixed $scope) => 'local-default');
    $this->createUnleashFeature($this->featureName, enabled: true);

    /** @var UnleashDriver $driver */
    $driver = Feature::getDriver();

    expect($driver->defined())->toContain($this->featureName);

    $this->deleteUnleashFeature($this->featureName);
});
