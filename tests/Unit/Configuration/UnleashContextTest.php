<?php

use Henzeb\Pennant\Unleash\Configuration\UnleashContext;
use Unleash\Client\Enum\ContextField;
use Unleash\Client\Enum\Stickiness;
use Unleash\Client\Exception\InvalidValueException;

covers(UnleashContext::class);

it('makes a context with all supported values', function () {
    $context = UnleashContext::make(
        currentUserId: 'user-id',
        ipAddress: '127.0.0.1',
        sessionId: 'session-id',
        customContext: ['tenant' => 'tenant-id'],
        hostname: 'app-host',
        environment: 'testing',
        currentTime: '2026-06-30T12:00:00+00:00',
    );

    expect($context->getCurrentUserId())->toBe('user-id')
        ->and($context->getIpAddress())->toBe('127.0.0.1')
        ->and($context->getSessionId())->toBe('session-id')
        ->and($context->getCustomProperty('tenant'))->toBe('tenant-id')
        ->and($context->getHostname())->toBe('app-host')
        ->and($context->getEnvironment())->toBe('testing')
        ->and($context->getCurrentTime()->format(DATE_ATOM))->toBe('2026-06-30T12:00:00+00:00');
});

it('falls back to the server remote address when no ip address is given', function () {
    $_SERVER['REMOTE_ADDR'] = '203.0.113.42';

    expect(UnleashContext::make()->getIpAddress())->toBe('203.0.113.42');

    unset($_SERVER['REMOTE_ADDR']);
});

it('returns null for the ip address when none is given and none is available on the server', function () {
    unset($_SERVER['REMOTE_ADDR']);

    expect(UnleashContext::make()->getIpAddress())->toBeNull();
});

it('falls back to null for the session id when no php session is active', function () {
    expect(UnleashContext::make()->getSessionId())->toBeNull();
});

it('stores a null custom property value as an empty string', function () {
    $context = UnleashContext::make()->setCustomProperty('tenant', null);

    expect($context->getCustomProperty('tenant'))->toBe('');
});

it('falls back to the machine hostname when none is given', function () {
    expect(UnleashContext::make()->getHostname())->toBe(gethostname());
});

it('serializes all context values for feature scoping', function () {
    $context = UnleashContext::make(
        currentUserId: 'user-id',
        ipAddress: '127.0.0.1',
        sessionId: 'session-id',
        customContext: ['tenant' => 'tenant-id'],
        hostname: 'app-host',
        environment: 'testing',
    );

    expect(json_decode($context->featureScopeSerialize(), true))->toBe([
        'userId' => 'user-id',
        'ipAddress' => '127.0.0.1',
        'sessionId' => 'session-id',
        'environment' => 'testing',
        'customContext' => ['tenant' => 'tenant-id', 'hostname' => 'app-host'],
    ]);
});

it('throws when getting a custom property that does not exist', function () {
    UnleashContext::make()->getCustomProperty('unknown');
})->throws(InvalidValueException::class, "The custom context value 'unknown' does not exist");

it('checks whether a custom property exists', function () {
    $context = UnleashContext::make(customContext: ['tenant' => 'tenant-id']);

    expect($context->hasCustomProperty('tenant'))->toBeTrue()
        ->and($context->hasCustomProperty('unknown'))->toBeFalse();
});

it('silently ignores removing a custom property that does not exist by default', function () {
    $context = UnleashContext::make();

    expect($context->removeCustomProperty('unknown'))->toBe($context)
        ->and($context->hasCustomProperty('unknown'))->toBeFalse();
});

it('throws when removing a custom property that does not exist and silent is false', function () {
    UnleashContext::make()->removeCustomProperty('unknown', false);
})->throws(InvalidValueException::class, "The custom context value 'unknown' does not exist");

it('removes an existing custom property', function () {
    $context = UnleashContext::make(customContext: ['tenant' => 'tenant-id']);

    $context->removeCustomProperty('tenant');

    expect($context->hasCustomProperty('tenant'))->toBeFalse();
});

it('sets the current user id', function () {
    $context = UnleashContext::make();

    expect($context->setCurrentUserId('user-id'))->toBe($context)
        ->and($context->getCurrentUserId())->toBe('user-id');
});

it('sets the ip address', function () {
    $context = UnleashContext::make();

    expect($context->setIpAddress('127.0.0.1'))->toBe($context)
        ->and($context->getIpAddress())->toBe('127.0.0.1');
});

it('sets the session id', function () {
    $context = UnleashContext::make();

    expect($context->setSessionId('session-id'))->toBe($context)
        ->and($context->getSessionId())->toBe('session-id');
});

it('sets the environment', function () {
    $context = UnleashContext::make();

    expect($context->setEnvironment('production'))->toBe($context)
        ->and($context->getEnvironment())->toBe('production');
});

it('sets the hostname and removes it when set to null', function () {
    $context = UnleashContext::make(hostname: 'app-host');

    expect($context->getHostname())->toBe('app-host');

    $context->setHostname(null);

    expect($context->getHostname())->toBe(gethostname());
});

it('sets and unsets the current time', function () {
    $context = UnleashContext::make();

    $context->setCurrentTime(new DateTimeImmutable('2026-06-30T12:00:00+00:00'));

    expect($context->getCurrentTime()->format(DATE_ATOM))->toBe('2026-06-30T12:00:00+00:00');

    $context->setCurrentTime(null);

    expect($context->getCurrentTime()->format(DATE_ATOM))->not->toBe('2026-06-30T12:00:00+00:00');
});

it('returns the custom properties', function () {
    $context = UnleashContext::make(customContext: ['tenant' => 'tenant-id']);

    expect($context->getCustomProperties())->toBe(['tenant' => 'tenant-id']);
});

it('matches a field value against the given values', function () {
    $context = UnleashContext::make(currentUserId: 'user-id');

    expect($context->hasMatchingFieldValue(ContextField::USER_ID, ['user-id', 'other']))->toBeTrue()
        ->and($context->hasMatchingFieldValue(ContextField::USER_ID, ['other']))->toBeFalse();
});

it('does not match a field value when it is not set', function () {
    $context = UnleashContext::make();

    expect($context->hasMatchingFieldValue(ContextField::USER_ID, ['user-id']))->toBeFalse();
});

it('does not match a field value when it is not set, even if null is among the given values', function () {
    $context = UnleashContext::make();

    expect($context->hasMatchingFieldValue(ContextField::USER_ID, [null]))->toBeFalse();
});

it('finds context values for the known context fields', function () {
    $context = UnleashContext::make(
        currentUserId: 'user-id',
        ipAddress: '127.0.0.1',
        sessionId: 'session-id',
        environment: 'testing',
        currentTime: '2026-06-30T12:00:00+00:00',
    );

    expect($context->findContextValue(ContextField::USER_ID))->toBe('user-id')
        ->and($context->findContextValue(Stickiness::USER_ID))->toBe('user-id')
        ->and($context->findContextValue(ContextField::SESSION_ID))->toBe('session-id')
        ->and($context->findContextValue(Stickiness::SESSION_ID))->toBe('session-id')
        ->and($context->findContextValue(ContextField::IP_ADDRESS))->toBe('127.0.0.1')
        ->and($context->findContextValue(ContextField::ENVIRONMENT))->toBe('testing')
        ->and($context->findContextValue(ContextField::CURRENT_TIME))->toBe('2026-06-30T12:00:00+0000');
});

it('finds context values for custom properties and returns null when missing', function () {
    $context = UnleashContext::make(customContext: ['tenant' => 'tenant-id']);

    expect($context->findContextValue('tenant'))->toBe('tenant-id')
        ->and($context->findContextValue('unknown'))->toBeNull();
});

it('defaults the current time to now when none is set', function () {
    $before = new DateTimeImmutable();

    $currentTime = UnleashContext::make()->getCurrentTime();

    expect($currentTime)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($currentTime->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp());
});
