<?php

use Henzeb\Pennant\Unleash\Drivers\UnleashDriver;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;

function writeDevelopmentBootstrapFile(string $name, array $features): void
{
    Storage::put($name, json_encode(['features' => $features]));
}

beforeEach(function () {
    config()->set('pennant.default', 'unleash');
    config()->set('unleash.development', true);

    Storage::fake();

    $this->bootstrapFileName = 'unleash-features.json';
    config()->set('unleash.bootstrap_file', Storage::path($this->bootstrapFileName));
});

it('evaluates a feature definition without strategies as unconditionally on or off', function () {
    writeDevelopmentBootstrapFile($this->bootstrapFileName, [
        ['name' => 'enabled-feature', 'enabled' => true, 'strategies' => []],
        ['name' => 'disabled-feature', 'enabled' => false, 'strategies' => []],
    ]);

    expect(Feature::active('enabled-feature'))->toBeTrue()
        ->and(Feature::active('disabled-feature'))->toBeFalse();
});

it('evaluates a feature definition with strategy constraints', function () {
    writeDevelopmentBootstrapFile($this->bootstrapFileName, [
        [
            'name' => 'targeted-feature',
            'enabled' => true,
            'strategies' => [
                [
                    'name' => 'default',
                    'parameters' => (object) [],
                    'constraints' => [
                        ['contextName' => 'scope', 'operator' => 'IN', 'values' => ['42']],
                    ],
                ],
            ],
        ],
    ]);

    expect(Feature::for('41')->active('targeted-feature'))->toBeFalse()
        ->and(Feature::for('42')->active('targeted-feature'))->toBeTrue();
});

it('evaluates a feature definition with variants', function () {
    writeDevelopmentBootstrapFile($this->bootstrapFileName, [
        [
            'name' => 'variant-feature',
            'enabled' => true,
            'strategies' => [],
            'variants' => [
                ['name' => 'red', 'weight' => 500, 'stickiness' => 'default'],
                ['name' => 'blue', 'weight' => 500, 'stickiness' => 'default'],
            ],
        ],
    ]);

    expect(Feature::value('variant-feature'))->toBeIn(['red', 'blue']);
});

it('resolves to false for a feature missing from an otherwise valid, empty features file', function () {
    writeDevelopmentBootstrapFile($this->bootstrapFileName, []);

    expect(Feature::active('missing-feature'))->toBeFalse();
});

it('throws when development mode is enabled without a features file configured', function () {
    config()->set('unleash.bootstrap_file', null);

    Feature::active('missing-feature');
})->throws(LogicException::class);

it('throws when the configured features file does not exist', function () {
    config()->set('unleash.bootstrap_file', Storage::path('missing-file.json'));

    Feature::active('missing-feature');
})->throws(InvalidArgumentException::class);

it('does not require app_url, api_key, or instance_id in development mode', function () {
    config()->set('unleash.app_url', '');
    config()->set('unleash.api_key', '');
    config()->set('unleash.instance_id', '');

    writeDevelopmentBootstrapFile($this->bootstrapFileName, [
        ['name' => 'enabled-feature', 'enabled' => true, 'strategies' => []],
    ]);

    expect(Feature::active('enabled-feature'))->toBeTrue();
});

it('reads the bootstrap file from a nested directory', function () {
    $name = 'nested/directory/unleash-features.json';
    config()->set('unleash.bootstrap_file', Storage::path($name));

    writeDevelopmentBootstrapFile($name, [
        ['name' => 'enabled-feature', 'enabled' => true, 'strategies' => []],
    ]);

    expect(Feature::active('enabled-feature'))->toBeTrue();
});
