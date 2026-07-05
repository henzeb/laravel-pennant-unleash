<?php

use Laravel\Pennant\Feature;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();
    $this->createUnleashFeature($this->featureName, enabled: true);
});

afterEach(function () {
    $this->deleteUnleashFeature($this->featureName);
});

it('returns the variant name when there is no payload', function () {
    $this->addUnleashVariant($this->featureName, 'my-variant');

    expect(Feature::value($this->featureName))->toBe('my-variant');
});

it('returns the string payload value', function () {
    $this->addUnleashVariant($this->featureName, 'my-variant', ['type' => 'string', 'value' => 'hello']);

    expect(Feature::value($this->featureName))->toBe('hello');
});

it('returns the decoded json payload', function () {
    $this->addUnleashVariant($this->featureName, 'my-variant', ['type' => 'json', 'value' => '{"foo":"bar"}']);

    expect(Feature::value($this->featureName))->toBe(['foo' => 'bar']);
});

it('returns the csv payload value', function () {
    $this->addUnleashVariant($this->featureName, 'my-variant', ['type' => 'csv', 'value' => 'a,b,c']);

    expect(Feature::value($this->featureName))->toBe('a,b,c');
});

it('returns false for a feature with variants that is globally disabled', function () {
    $this->disableUnleashFeature($this->featureName);
    $this->addUnleashVariant($this->featureName, 'my-variant');

    expect(Feature::value($this->featureName))->toBeFalse();
});
