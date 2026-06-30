<?php

use Henzeb\Pennant\Unleash\Configuration\UnleashContext;

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
