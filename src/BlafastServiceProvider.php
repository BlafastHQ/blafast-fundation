<?php

declare(strict_types=1);

namespace Blafast\Foundation;

use Blafast\Foundation\Commands\BlafastCommand;
use Blafast\Foundation\Database\Concerns\HasOrganizationColumn;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BlafastServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('blafast-fundation')
            ->hasConfigFile('blafast-fundation')
            ->hasViews()
            ->hasRoute('api')
            ->hasMigrations([
                'create_organizations_table',
                'create_organization_user_table',
                'create_addresses_table',
                'create_countries_table',
                'create_currencies_table',
                'create_system_settings_table',
                'create_deferred_endpoint_configs_table',
                'create_deferred_api_requests_table',
            ])
            ->runsMigrations()
            ->hasCommand(BlafastCommand::class);
    }

    /**
     * Register any package services.
     */
    public function packageRegistered(): void
    {
        // Merge package configuration with application config
        $this->mergeConfigFrom(
            __DIR__.'/../config/blafast-fundation.php',
            'blafast-fundation'
        );

        // Register OrganizationContext as a scoped singleton (per-request)
        $this->app->scoped(OrganizationContext::class, function () {
            return new OrganizationContext;
        });

        // Register the migration helper for Blueprint macros
        $this->app->register(HasOrganizationColumn::class);
    }

    /**
     * Bootstrap any package services.
     */
    public function packageBooted(): void
    {
        // Configure rate limiting for authentication endpoints
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Register morph map for polymorphic relationships
        $morphMap = [
            'organization' => \Blafast\Foundation\Models\Organization::class,
        ];

        // In testing environment, use test fixtures
        if ($this->app->environment('testing')) {
            if (class_exists(\Blafast\Foundation\Tests\Fixtures\User::class)) {
                $morphMap['user'] = \Blafast\Foundation\Tests\Fixtures\User::class;
            }
            if (class_exists(\Blafast\Foundation\Tests\Fixtures\AddressableModel::class)) {
                $morphMap['addressable_model'] = \Blafast\Foundation\Tests\Fixtures\AddressableModel::class;
            }
        } elseif (class_exists(\App\Models\User::class)) {
            // Add User class if it exists in non-testing environment
            $morphMap['user'] = \App\Models\User::class;
        }

        Relation::enforceMorphMap($morphMap);

        // Register publishable resources
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/blafast-fundation.php' => config_path('blafast-fundation.php'),
            ], 'blafast-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'blafast-migrations');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/blafast-fundation'),
            ], 'blafast-views');
        }

        // Register routes if they exist
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Register views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blafast-fundation');

        // Register translations if needed
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'blafast-fundation');
    }
}
