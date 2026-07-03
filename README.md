# Unleash driver for Laravel Pennant

[![Build Status](https://github.com/henzeb/laravel-pennant-unleash/workflows/tests/badge.svg)](https://github.com/henzeb/laravel-pennant-unleash/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/henzeb/laravel-pennant-unleash.svg?style=flat-square)](https://packagist.org/packages/henzeb/laravel-pennant-unleash)
[![Total Downloads](https://img.shields.io/packagist/dt/henzeb/laravel-pennant-unleash.svg?style=flat-square)](https://packagist.org/packages/henzeb/laravel-pennant-unleash)
[![License](https://img.shields.io/packagist/l/henzeb/laravel-pennant-unleash)](https://packagist.org/packages/henzeb/laravel-pennant-unleash)

[Laravel Pennant](https://laravel.com/docs/master/pennant) is a lightweight feature flag package, but its built-in
drivers store state locally. [Unleash](https://www.getunleash.io/) is a feature management platform that evaluates
flags server-side with rich targeting strategies.

This package bridges the two: it registers an `unleash` Pennant driver so you can use the full Pennant API while
Unleash handles all flag evaluation.

## Table of contents

- [Installation](#installation)
  - [Custom client builder](#custom-client-builder)
- [Context](#context)
  - [Authenticated users](#authenticated-users)
  - [Eloquent models](#eloquent-models)
  - [Plain strings](#plain-strings)
  - [UnleashContext](#unleashcontext)
  - [FeatureScopeable](#featurescopeable)
  - [Custom context resolver](#custom-context-resolver)
- [Variants](#variants)
- [Defining local fallbacks](#defining-local-fallbacks)
- [Development mode](#development-mode)
- [Testing this package](#testing-this-package)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Installation

```bash
composer require henzeb/laravel-pennant-unleash
```

Publish the config file:

```bash
php artisan vendor:publish --tag=laravel-pennant-unleash
```

To use Unleash with Pennant, you must add an `unleash` entry in the `stores`
section of `config/pennant.php`, equivalent to adding this yourself:

```php
'stores' => [
    'unleash' => [
        'driver' => 'unleash',
    ],

    // Pennant's built-in stores, e.g.:
    'array' => [
        'driver' => 'array',
    ],
],
```

In your `.env`, add the following. Use your own credentials, of course.

```dotenv
UNLEASH_URL=https://your-unleash-instance/api
UNLEASH_API_KEY=your-client-api-key
UNLEASH_INSTANCE_ID=your-instance-id
UNLEASH_APP_NAME=your-app-name        # defaults to APP_NAME
UNLEASH_CACHE_DRIVER=array            # defaults to CACHE_DRIVER
```

### Custom client builder

If you have specific needs for the client builder, you can use `Feature::buildUnleashClientUsing()` to customize the
UnleashBuilder.

```php
use Unleash\Client\UnleashBuilder;

Feature::buildUnleashClientUsing(function (UnleashBuilder $builder): UnleashBuilder {
    return $builder->withStrategy(new MyCustomStrategy());
});
```

Or return a completely new `UnleashBuilder` instance to bypass the package's own configuration:

```php
use Unleash\Client\UnleashBuilder;

Feature::buildUnleashClientUsing(function (UnleashBuilder $builder): UnleashBuilder {
    return UnleashBuilder::create()
        ->withAppUrl(config('unleash.app_url'))
        ->withInstanceId(config('unleash.instance_id'))
        ->withAppName(config('unleash.app_name'));
});
```

## Context

The scope passed to Pennant is turned into an `UnleashContext`, so it can be matched by strategy constraints in
the Unleash admin UI.

### Authenticated users

Pass a user (or anything implementing `Illuminate\Contracts\Auth\Authenticatable`) and the driver sends the auth
identifier as the Unleash `currentUserId`. In Unleash, target these with a **userId** constraint.

```php
Feature::for($user)->active('my-feature');
```

### Eloquent models

Pass an Eloquent model and the driver sends its class (or morph map alias, if configured) and key as custom
context properties. In Unleash, target these with **class** and **id** constraints.

```php
Feature::for($tenant)->active('my-feature');
```

For example, `$tenant = App\Models\Tenant::find(42)` sends:

```php
[
    'class' => 'App\Models\Tenant', // or the morph map alias, e.g. 'tenant'
    'id' => '42',
]
```

so an Unleash strategy constraint on `class` with value `App\Models\Tenant` (or your morph map alias) combined
with an `id` constraint on `42` will match this scope.

### Plain strings

Pass a plain string and the driver sends it as a custom context property. In Unleash, target these with a
**scope** constraint.

```php
Feature::for('some-identifier')->active('my-feature');
```

For example, `Feature::for('some-identifier')` sends:

```php
[
    'scope' => 'some-identifier',
]
```

so an Unleash strategy constraint on `scope` with value `some-identifier` will match this scope.

### UnleashContext

`Henzeb\Pennant\Unleash\Configuration\UnleashContext` is a Pennant-aware context object you can construct and pass
as the scope directly, to set any Unleash context field (`userId`, `sessionId`, `ipAddress`, `environment`,
`currentTime`, custom properties) yourself.

```php
use Henzeb\Pennant\Unleash\Configuration\UnleashContext;

$context = new UnleashContext(
    currentUserId: '42',
    ipAddress: '1.2.3.4',
    sessionId: 'abc123',
    customContext: ['region' => 'eu-west'],
);

Feature::for($context)->active('my-feature');
```

It also has a static `make` factory and supports Laravel's `Conditionable` trait:

```php
$context = UnleashContext::make(currentUserId: '42')
    ->when($request->has('region'), fn($ctx) => $ctx->setCustomProperty('region', $request->region));
```

### FeatureScopeable

Any object can become scopable by implementing `Laravel\Pennant\Contracts\FeatureScopeable`. Pennant calls
`toFeatureIdentifier` before passing the scope to the driver, so the driver receives whatever you return from it.
Return an `UnleashContext` (or any `Unleash\Client\Configuration\Context`) to have it used for flag evaluation.

This is the right approach when you have domain objects — such as a `Tenant` or `Team` — that you want to pass
directly as a scope without registering a global context resolver.

```php
use Henzeb\Pennant\Unleash\Configuration\UnleashContext;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Unleash\Client\Configuration\Context;

class Tenant implements FeatureScopeable
{
    public function toFeatureIdentifier(string $driver): mixed
    {
        return match ($driver) {
            'unleash' => UnleashContext::make(customContext: ['tenantId' => (string) $this->id]),
            default   => $this->id,
        };
    }
}

Feature::for($tenant)->active('my-feature');
```

### Custom context resolver

If you need to map an arbitrary scope to an Unleash context, register a resolver in a service provider:

```php
use Henzeb\Pennant\Unleash\Configuration\UnleashContext;
use Unleash\Client\Configuration\Context;

Feature::resolveUnleashContextUsing(function (mixed $scope): ?Context {
    if ($scope instanceof Tenant) {
        return UnleashContext::make(customContext: ['tenantId' => $scope->id]);
    }

    return null;
});
```

Returning `null` sends no context to Unleash.

## Variants

`Feature::value('my-feature')` resolves the [Unleash variant](https://docs.getunleash.io/reference/feature-toggle-variants)
for the given feature and scope:

- If the feature (or the matched variant) is disabled, it returns `false`.
- If the variant has no payload, it returns the variant's name.
- If the variant has a `string` or `csv` payload, it returns the raw payload value as a string.
- If the variant has a `json` payload, it returns the decoded value (array).

```php
Feature::value('my-feature');
// 'my-variant'             — variant with no payload
// 'hello'                  — variant with a string/csv payload
// ['foo' => 'bar']         — variant with a json payload
```

## Defining local fallbacks

Because this driver reads flags from Unleash, `Feature::define()` doesn't register an initial value the way it does
for Pennant's built-in drivers. Instead, it registers a **fallback** resolver that only runs when the feature
doesn't exist in Unleash yet — for example, before the toggle has been created there, or in an environment where
it hasn't been rolled out. Once the toggle exists in Unleash, the fallback is ignored and Unleash's evaluation is
used instead.

Without a fallback, a feature that doesn't exist in Unleash simply resolves to `false`. A fallback is only useful
when you need something other than that:

- a **fail-open** default (e.g. `true`) for a toggle that should behave as enabled until it's deliberately created
  and configured in Unleash;
- a **non-boolean default**, since without a fallback an undefined feature resolves to the boolean `false`, which
  won't match code expecting a string or decoded JSON payload from `Feature::value()`;
- an **environment-specific default**, e.g. `true` locally while every environment that matters defaults to `false`
  in production until someone enables the toggle there.

```php
Feature::define('my-feature', fn (mixed $scope) => true);
```

Register this in a service provider's `boot()` method, same as any other `Feature::define()` call.

A feature that only has a local fallback (and doesn't exist in Unleash) won't show up in `Feature::for($scope)->all()`
— it's only used when the feature is checked directly, e.g. via `Feature::active()` or `Feature::value()`.

## Development mode

If you don't have an Unleash server available for local development at all, you may enable development mode
instead, which makes the driver evaluate features from a local JSON file and never contact Unleash:

```dotenv
UNLEASH_DEVELOPMENT=true
UNLEASH_BOOTSTRAP_FILE=/path/to/unleash-features.json
```

The file must contain feature definitions in the same shape Unleash's own
[client feature API](https://docs.getunleash.io/reference/api/unleash/features) returns them — this is passed
directly to the underlying SDK's own
[bootstrap](https://docs.getunleash.io/sdks/php#bootstrap) support, so strategies, constraints, and variants are
evaluated exactly as they would be against a real server:

```json
{
    "features": [
        {
            "name": "my-feature",
            "enabled": true,
            "strategies": [
                {
                    "name": "default",
                    "parameters": {},
                    "constraints": [
                        { "contextName": "scope", "operator": "IN", "values": ["42"] }
                    ]
                }
            ]
        }
    ]
}
```

While development mode is enabled, no `app_url`, `api_key`, or `instance_id` configuration is required. A feature
not present in the file still falls back to any resolver you registered with `Feature::define()`, exactly as it
would against a real server; if neither applies, it simply resolves to `false`.

If development mode is enabled without `unleash.bootstrap_file` configured, or the configured file doesn't exist,
an exception is thrown — this is a misconfiguration, not a state the driver silently works around.

## Testing this package

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email henzeberkheij@gmail.com instead of using the issue tracker.

## Credits

- [Henze Berkheij](https://github.com/henzeb)

## License

The MIT License. Please see [License File](LICENSE.md) for more information.
