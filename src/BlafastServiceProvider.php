<?php

declare(strict_types=1);

namespace Blafast\Foundation;

use Blafast\Foundation\Commands\BlafastCommand;
use Blafast\Foundation\Commands\CleanupActivityLogCommand;
use Blafast\Foundation\Commands\MetadataCacheCommand;
use Blafast\Foundation\Commands\QueueStatusCommand;
use Blafast\Foundation\Commands\RetryFailedJobsCommand;
use Blafast\Foundation\Database\Concerns\HasOrganizationColumn;
use Blafast\Foundation\Exceptions\JsonApiExceptionHandler;
use Blafast\Foundation\Http\Middleware\AddRateLimitHeaders;
use Blafast\Foundation\Http\Middleware\EnsureOrganizationContext;
use Blafast\Foundation\Http\Middleware\ResolveOrganizationContext;
use Blafast\Foundation\Events\JobFailed;
use Blafast\Foundation\Listeners\InvalidateMetadataCacheOnModelUpdate;
use Blafast\Foundation\Listeners\InvalidateMetadataCacheOnPermissionChange;
use Blafast\Foundation\Listeners\NotifySuperadminsOnJobFailure;
use Blafast\Foundation\Models\Activity;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Policies\ActivityPolicy;
use Blafast\Foundation\Policies\OrganizationPolicy;
use Blafast\Foundation\Providers\RateLimitServiceProvider;
use Blafast\Foundation\Providers\ResponseMacroServiceProvider;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Services\PaginationService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
            ->hasConfigFile(['blafast-fundation', 'permission', 'auth', 'sanctum', 'jsonapi', 'media-library', 'activitylog', 'queue'])
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
                'create_permission_tables',
                'create_media_table',
                'create_activity_log_table',
                'create_notifications_table',
                'create_jobs_table',
            ])
            ->runsMigrations()
            ->hasCommands([
                BlafastCommand::class,
                MetadataCacheCommand::class,
                CleanupActivityLogCommand::class,
                QueueStatusCommand::class,
                RetryFailedJobsCommand::class,
            ]);
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

        // Register PaginationService as a singleton
        $this->app->singleton(PaginationService::class);

        // Register ModelRegistry as a singleton
        $this->app->singleton(\Blafast\Foundation\Services\ModelRegistry::class);

        // Register ModelMetaService as a singleton
        $this->app->singleton(\Blafast\Foundation\Services\ModelMetaService::class);

        // Register MetadataCacheService as a singleton
        $this->app->singleton(MetadataCacheService::class);

        // Register MenuRegistry as a singleton
        $this->app->singleton(\Blafast\Foundation\Services\MenuRegistry::class);

        // Register MenuService as a singleton
        $this->app->singleton(\Blafast\Foundation\Services\MenuService::class);

        // Register QueryBuilderService as a singleton
        $this->app->singleton(\Blafast\Foundation\Services\QueryBuilderService::class);

        // Register FileService as a singleton
        $this->app->singleton(\Blafast\Foundation\Services\FileService::class);

        // Register the migration helper for Blueprint macros
        $this->app->register(HasOrganizationColumn::class);
    }

    /**
     * Bootstrap any package services.
     */
    public function packageBooted(): void
    {
        // Register middleware aliases
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('org.resolve', ResolveOrganizationContext::class);
        $router->aliasMiddleware('org.required', EnsureOrganizationContext::class);
        $router->aliasMiddleware('rate-limit-headers', AddRateLimitHeaders::class);

        // Register response macros
        $this->app->register(ResponseMacroServiceProvider::class);

        // Register rate limiting
        $this->app->register(RateLimitServiceProvider::class);

        // Register dynamic route macros
        $this->app->register(\Blafast\Foundation\Providers\DynamicRouteServiceProvider::class);

        // Register policies
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Register JSON:API exception handler
        $this->registerExceptionHandler();

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

        // Register cache invalidation event listeners
        $this->registerCacheInvalidationListeners();

        // Register queue event listeners
        $this->registerQueueEventListeners();

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

    /**
     * Register the JSON:API exception handler.
     */
    protected function registerExceptionHandler(): void
    {
        $this->app->singleton(JsonApiExceptionHandler::class);

        // Extend the exception handler to use our JSON:API handler for API requests
        $this->app->extend(ExceptionHandler::class, function (ExceptionHandler $handler) {
            $jsonApiHandler = $this->app->make(JsonApiExceptionHandler::class);

            $handler->renderable(function (\Throwable $e, \Illuminate\Http\Request $request) use ($jsonApiHandler) {
                return $jsonApiHandler->render($request, $e);
            });

            return $handler;
        });
    }

    /**
     * Register cache invalidation event listeners.
     */
    protected function registerCacheInvalidationListeners(): void
    {
        // Listen to Eloquent model events for cache invalidation
        Event::listen('eloquent.updated:*', InvalidateMetadataCacheOnModelUpdate::class);
        Event::listen('eloquent.created:*', InvalidateMetadataCacheOnModelUpdate::class);
        Event::listen('eloquent.deleted:*', InvalidateMetadataCacheOnModelUpdate::class);

        // Listen to Spatie permission package events for cache invalidation
        // These events are fired when roles/permissions are assigned to users
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            Event::listen('permission.attached', InvalidateMetadataCacheOnPermissionChange::class);
            Event::listen('permission.detached', InvalidateMetadataCacheOnPermissionChange::class);
            Event::listen('role.attached', InvalidateMetadataCacheOnPermissionChange::class);
            Event::listen('role.detached', InvalidateMetadataCacheOnPermissionChange::class);
        }
    }

    /**
     * Register queue event listeners.
     */
    protected function registerQueueEventListeners(): void
    {
        // Listen to JobFailed event to notify superadmins
        Event::listen(JobFailed::class, NotifySuperadminsOnJobFailure::class);
    }
}
