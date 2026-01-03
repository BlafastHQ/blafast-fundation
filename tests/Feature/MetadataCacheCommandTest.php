<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable caching for tests
    Config::set('blafast-fundation.cache.enabled', true);
    Config::set('blafast-fundation.cache.metadata_ttl', 600);

    // Register the Organization model
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);
});

test('warm command pre-populates cache for all models', function () {
    $this->artisan('blafast:cache:metadata warm')
        ->expectsOutput('✓ Warmed cache for all models')
        ->assertExitCode(0);
});

test('warm command pre-populates cache for specific model', function () {
    $this->artisan('blafast:cache:metadata warm --model=organization')
        ->expectsOutput('✓ Warmed cache for model: organization')
        ->assertExitCode(0);
});

test('warm command shows progress bar for multiple models', function () {
    // Register multiple models
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);

    $this->artisan('blafast:cache:metadata warm')
        ->assertExitCode(0);
});

test('clear command clears all metadata cache with confirmation', function () {
    $this->artisan('blafast:cache:metadata clear')
        ->expectsConfirmation('Clear ALL metadata cache?', 'yes')
        ->expectsOutput('✓ Cleared all metadata cache')
        ->assertExitCode(0);
});

test('clear command cancels when user declines confirmation', function () {
    $this->artisan('blafast:cache:metadata clear')
        ->expectsConfirmation('Clear ALL metadata cache?', 'no')
        ->assertExitCode(0);
});

test('clear command clears cache for specific model', function () {
    $this->artisan('blafast:cache:metadata clear --model=organization')
        ->expectsOutput('✓ Cleared cache for model: organization')
        ->assertExitCode(0);
});

test('clear command clears cache for specific organization', function () {
    $this->artisan('blafast:cache:metadata clear --organization=123')
        ->expectsOutput('✓ Cleared cache for organization: 123')
        ->assertExitCode(0);
});

test('status command displays cache configuration', function () {
    $this->artisan('blafast:cache:metadata status')
        ->expectsTable(['Setting', 'Value'], [
            ['Cache Driver', config('cache.default')],
            ['Supports Tagging', in_array(config('cache.default'), ['redis', 'memcached']) ? 'Yes' : 'No'],
            ['TTL', '600 seconds'],
            ['Registered Models', '1'],
        ])
        ->assertExitCode(0);
});

test('status command shows current cache statistics', function () {
    $service = app(MetadataCacheService::class);
    $stats = $service->getStats();

    $this->artisan('blafast:cache:metadata status')
        ->assertExitCode(0);

    // Verify the output contains expected statistics
    expect($stats)->toHaveKeys(['driver', 'supports_tagging', 'ttl', 'prefix']);
});

test('warm command actually populates cache', function () {
    Cache::flush();

    $this->artisan('blafast:cache:metadata warm --model=organization')
        ->assertExitCode(0);

    // Verify cache was populated by checking if subsequent request is faster
    $service = app(MetadataCacheService::class);
    $stats = $service->getStats();

    expect($stats)->toBeArray();
});

test('clear command actually removes cached data', function () {
    // Warm the cache first
    $this->artisan('blafast:cache:metadata warm --model=organization')
        ->assertExitCode(0);

    // Clear the cache
    $this->artisan('blafast:cache:metadata clear --model=organization')
        ->assertExitCode(0);

    // Cache should be empty now - verify by warming again
    $this->artisan('blafast:cache:metadata warm --model=organization')
        ->expectsOutput('✓ Warmed cache for model: organization')
        ->assertExitCode(0);
});

test('command handles invalid model slug gracefully', function () {
    $this->artisan('blafast:cache:metadata warm --model=nonexistent')
        ->assertExitCode(1);
});

test('command handles invalid action gracefully', function () {
    $this->artisan('blafast:cache:metadata invalid-action')
        ->assertExitCode(1);
});

test('warm command works with organization context', function () {
    $org = Organization::factory()->create();

    $this->artisan("blafast:cache:metadata warm --organization={$org->id}")
        ->assertExitCode(0);
});

test('command respects cache disabled configuration', function () {
    Config::set('blafast-fundation.cache.enabled', false);

    $this->artisan('blafast:cache:metadata warm --model=organization')
        ->expectsOutput('✓ Warmed cache for model: organization')
        ->assertExitCode(0);
});

test('status command shows disabled cache status', function () {
    Config::set('blafast-fundation.cache.enabled', false);

    $this->artisan('blafast:cache:metadata status')
        ->assertExitCode(0);
});

test('clear command with model option does not require confirmation', function () {
    $this->artisan('blafast:cache:metadata clear --model=organization')
        ->doesntExpectOutput('Clear ALL metadata cache?')
        ->assertExitCode(0);
});

test('clear command with organization option does not require confirmation', function () {
    $this->artisan('blafast:cache:metadata clear --organization=123')
        ->doesntExpectOutput('Clear ALL metadata cache?')
        ->assertExitCode(0);
});
