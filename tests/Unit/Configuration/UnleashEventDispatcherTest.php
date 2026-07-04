<?php

use Henzeb\Pennant\Unleash\Configuration\UnleashEventDispatcher;
use Henzeb\Pennant\Unleash\Events\FeatureToggleDisabled;
use Henzeb\Pennant\Unleash\Events\FeatureToggleMissingStrategyHandler;
use Henzeb\Pennant\Unleash\Events\FeatureToggleNotFound;
use Henzeb\Pennant\Unleash\Events\FetchingDataFailed;
use Henzeb\Pennant\Unleash\Events\ImpressionDataReceived;
use Illuminate\Support\Facades\Event;
use Unleash\Client\Configuration\UnleashContext;
use Unleash\Client\Configuration\UnleashConfiguration;
use Unleash\Client\DTO\DefaultVariant;
use Unleash\Client\DTO\DefaultFeature;
use Unleash\Client\Event\FeatureToggleDisabledEvent;
use Unleash\Client\Event\FeatureToggleMissingStrategyHandlerEvent;
use Unleash\Client\Event\FeatureToggleNotFoundEvent;
use Unleash\Client\Event\FetchingDataFailedEvent;
use Unleash\Client\Event\ImpressionDataEvent;
use Unleash\Client\Event\UnleashEvents;

covers(UnleashEventDispatcher::class);

it('subscribes to every unleash sdk event', function () {
    expect(UnleashEventDispatcher::getSubscribedEvents())->toBe([
        UnleashEvents::FEATURE_TOGGLE_NOT_FOUND => 'onFeatureToggleNotFound',
        UnleashEvents::FEATURE_TOGGLE_DISABLED => 'onFeatureToggleDisabled',
        UnleashEvents::FEATURE_TOGGLE_MISSING_STRATEGY_HANDLER => 'onFeatureToggleMissingStrategyHandler',
        UnleashEvents::FETCHING_DATA_FAILED => 'onFetchingDataFailed',
        UnleashEvents::IMPRESSION_DATA => 'onImpressionData',
    ]);
});

it('dispatches a FeatureToggleNotFound laravel event with the sdk event data', function () {
    Event::fake();

    $context = new UnleashContext(currentUserId: '42');

    (new UnleashEventDispatcher())->onFeatureToggleNotFound(
        new FeatureToggleNotFoundEvent($context, 'my-feature')
    );

    Event::assertDispatched(FeatureToggleNotFound::class, fn(FeatureToggleNotFound $event) => $event->context === $context
        && $event->featureName === 'my-feature');
});

it('dispatches a FeatureToggleDisabled laravel event with the sdk event data', function () {
    Event::fake();

    $context = new UnleashContext(currentUserId: '42');
    $feature = new DefaultFeature('my-feature', false, []);

    (new UnleashEventDispatcher())->onFeatureToggleDisabled(
        new FeatureToggleDisabledEvent($feature, $context)
    );

    Event::assertDispatched(FeatureToggleDisabled::class, fn(FeatureToggleDisabled $event) => $event->feature === $feature
        && $event->context === $context);
});

it('dispatches a FeatureToggleMissingStrategyHandler laravel event with the sdk event data', function () {
    Event::fake();

    $context = new UnleashContext(currentUserId: '42');
    $feature = new DefaultFeature('my-feature', true, []);

    (new UnleashEventDispatcher())->onFeatureToggleMissingStrategyHandler(
        new FeatureToggleMissingStrategyHandlerEvent($context, $feature)
    );

    Event::assertDispatched(
        FeatureToggleMissingStrategyHandler::class,
        fn(FeatureToggleMissingStrategyHandler $event) => $event->context === $context && $event->feature === $feature
    );
});

it('dispatches a FetchingDataFailed laravel event with the sdk event data', function () {
    Event::fake();

    $exception = new Exception('connection refused');

    (new UnleashEventDispatcher())->onFetchingDataFailed(
        new FetchingDataFailedEvent($exception)
    );

    Event::assertDispatched(FetchingDataFailed::class, fn(FetchingDataFailed $event) => $event->exception === $exception);
});

it('dispatches an ImpressionDataReceived laravel event with the sdk event data', function () {
    Event::fake();

    $context = new UnleashContext(currentUserId: '42');
    $configuration = new UnleashConfiguration('https://unleash.test', 'my-app', 'instance-1');
    $feature = new DefaultFeature('my-feature', true, []);
    $variant = new DefaultVariant('my-variant', true);

    (new UnleashEventDispatcher())->onImpressionData(
        new ImpressionDataEvent('isEnabled', 'event-id-1', $configuration, $context, $feature, $variant)
    );

    Event::assertDispatched(
        ImpressionDataReceived::class,
        fn(ImpressionDataReceived $event) => $event->eventType === 'isEnabled'
            && $event->eventId === 'event-id-1'
            && $event->context['userId'] === '42'
            && $event->enabled === true
            && $event->featureName === 'my-feature'
            && $event->variant === 'my-variant'
    );
});
