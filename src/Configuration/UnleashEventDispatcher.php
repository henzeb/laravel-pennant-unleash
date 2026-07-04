<?php

declare(strict_types=1);

namespace Henzeb\Pennant\Unleash\Configuration;

use Henzeb\Pennant\Unleash\Events\FeatureToggleDisabled;
use Henzeb\Pennant\Unleash\Events\FeatureToggleMissingStrategyHandler;
use Henzeb\Pennant\Unleash\Events\FeatureToggleNotFound;
use Henzeb\Pennant\Unleash\Events\FetchingDataFailed;
use Henzeb\Pennant\Unleash\Events\ImpressionDataReceived;
use Illuminate\Support\Facades\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Unleash\Client\Event\FeatureToggleDisabledEvent;
use Unleash\Client\Event\FeatureToggleMissingStrategyHandlerEvent;
use Unleash\Client\Event\FeatureToggleNotFoundEvent;
use Unleash\Client\Event\FetchingDataFailedEvent;
use Unleash\Client\Event\ImpressionDataEvent;
use Unleash\Client\Event\UnleashEvents;

/**
 * Maps every event the Unleash SDK dispatches to a first-party Laravel
 * event, so consumers can listen for them the normal Laravel way
 * (Event::listen(), an EventServiceProvider, attributes, etc.) instead of
 * having to know Symfony's event dispatcher or the SDK's own event classes.
 */
class UnleashEventDispatcher implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UnleashEvents::FEATURE_TOGGLE_NOT_FOUND => 'onFeatureToggleNotFound',
            UnleashEvents::FEATURE_TOGGLE_DISABLED => 'onFeatureToggleDisabled',
            UnleashEvents::FEATURE_TOGGLE_MISSING_STRATEGY_HANDLER => 'onFeatureToggleMissingStrategyHandler',
            UnleashEvents::FETCHING_DATA_FAILED => 'onFetchingDataFailed',
            UnleashEvents::IMPRESSION_DATA => 'onImpressionData',
        ];
    }

    public function onFeatureToggleNotFound(FeatureToggleNotFoundEvent $event): void
    {
        Event::dispatch(new FeatureToggleNotFound($event->getContext(), $event->getFeatureName()));
    }

    public function onFeatureToggleDisabled(FeatureToggleDisabledEvent $event): void
    {
        Event::dispatch(new FeatureToggleDisabled($event->getFeature(), $event->getContext()));
    }

    public function onFeatureToggleMissingStrategyHandler(FeatureToggleMissingStrategyHandlerEvent $event): void
    {
        Event::dispatch(new FeatureToggleMissingStrategyHandler($event->getContext(), $event->getFeature()));
    }

    public function onFetchingDataFailed(FetchingDataFailedEvent $event): void
    {
        Event::dispatch(new FetchingDataFailed($event->getException()));
    }

    public function onImpressionData(ImpressionDataEvent $event): void
    {
        Event::dispatch(new ImpressionDataReceived(
            $event->getEventType(),
            $event->getEventId(),
            $event->getContext(),
            $event->isEnabled(),
            $event->getFeatureName(),
            $event->getVariant(),
        ));
    }
}
