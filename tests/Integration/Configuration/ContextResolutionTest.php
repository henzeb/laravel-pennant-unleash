<?php

use Henzeb\Pennant\Unleash\Configuration\UnleashContext;
use Henzeb\Pennant\Unleash\Drivers\UnleashDriver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Feature;
use Orchestra\Testbench\Factories\UserFactory;
use Tests\Fixtures\ScopedModel;
use Unleash\Client\Configuration\Context;

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    $this->featureName = 'feature-flag-' . uniqid();

    $this->user = UserFactory::new()->make(['id' => 1]);
    $this->otherUser = UserFactory::new()->make(['id' => 2]);
});

afterEach(function () {
    $this->deleteUnleashFeature($this->featureName);
    Relation::morphMap([], false);
});

it('resolves users to an unleash context', function () {
    $this->actingAs($this->user);
    $this->createUserWithIdFeature($this->featureName, (string) $this->user->getAuthIdentifier());

    expect(Feature::active($this->featureName))->toBeTrue()
        ->and(Feature::for($this->otherUser)->active($this->featureName))->toBeFalse();
});

it('uses an unleash context as the feature scope', function () {
    $this->createUserWithIdFeature($this->featureName, 'allowed-user');

    expect(Feature::for(UnleashContext::make(currentUserId: 'allowed-user'))->active($this->featureName))->toBeTrue()
        ->and(Feature::for(UnleashContext::make(currentUserId: 'other-user'))->active($this->featureName))->toBeFalse();
});

it('resolves a scopeable object returning an sdk context', function () {
    $scope = new class ('allowed-user') implements FeatureScopeable {
        public function __construct(private readonly string $userId) {}

        public function toFeatureIdentifier(string $driver): Context
        {
            return UnleashContext::make(currentUserId: $this->userId);
        }
    };

    $this->createUserWithIdFeature($this->featureName, 'allowed-user');

    expect(Feature::for($scope)->active($this->featureName))->toBeTrue()
        ->and(Feature::for(new $scope('other-user'))->active($this->featureName))->toBeFalse();
});

it('resolves a string scope to an unleash context using a custom scope property', function () {
    $this->createStringScopeFeature($this->featureName, 'allowed-scope');

    expect(Feature::for('allowed-scope')->active($this->featureName))->toBeTrue()
        ->and(Feature::for('other-scope')->active($this->featureName))->toBeFalse();
});

it('resolves an eloquent model to an unleash context using its full class name', function () {
    $model = (new ScopedModel())->forceFill(['id' => 1]);
    $otherModel = (new ScopedModel())->forceFill(['id' => 2]);

    $this->createModelScopeFeature($this->featureName, $model::class, (string) $model->getKey());

    expect(Feature::for($model)->active($this->featureName))->toBeTrue()
        ->and(Feature::for($otherModel)->active($this->featureName))->toBeFalse();
});

it('resolves an eloquent model to an unleash context using its morph map alias', function () {
    Relation::morphMap(['scoped-model' => ScopedModel::class]);

    $model = (new ScopedModel())->forceFill(['id' => 1]);
    $otherModel = (new ScopedModel())->forceFill(['id' => 2]);

    $this->createModelScopeFeature($this->featureName, 'scoped-model', (string) $model->getKey());

    expect(Feature::for($model)->active($this->featureName))->toBeTrue()
        ->and(Feature::for($otherModel)->active($this->featureName))->toBeFalse();
});

it('resolves a feature scope using a custom context resolver', function () {
    Feature::resolveUnleashContextUsing(
        fn(string $scope) => UnleashContext::make()
            ->when($scope, fn(UnleashContext $context, string $userId) => $context->setCurrentUserId($userId))
    );

    $this->createUserWithIdFeature($this->featureName, 'allowed-user');

    expect(Feature::for('allowed-user')->active($this->featureName))->toBeTrue()
        ->and(Feature::for('other-user')->active($this->featureName))->toBeFalse();
});

it('does not apply the context resolver to drivers other than the unleash driver', function () {
    config()->set('pennant.default', 'array');

    expect(fn() => Feature::resolveUnleashContextUsing(fn(string $scope) => $scope))
        ->not->toThrow(Throwable::class);
});
