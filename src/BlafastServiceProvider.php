<?php

declare(strict_types=1);

namespace Blafast\Foundation;

use App\Models\User;
use Blafast\Foundation\Commands\BlafastCommand;
use Blafast\Foundation\Commands\CleanupActivityLogCommand;
use Blafast\Foundation\Commands\DeferredCleanupCommand;
use Blafast\Foundation\Commands\MetadataCacheCommand;
use Blafast\Foundation\Commands\ModulesDiscoverCommand;
use Blafast\Foundation\Commands\ModulesListCommand;
use Blafast\Foundation\Commands\PermissionsMigrateSlugsCommand;
use Blafast\Foundation\Commands\PermissionsSyncCommand;
use Blafast\Foundation\Commands\QueueStatusCommand;
use Blafast\Foundation\Commands\RetryFailedJobsCommand;
use Blafast\Foundation\Commands\SchedulerHealthCheckCommand;
use Blafast\Foundation\Console\ScheduleServiceProvider;
use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Database\Concerns\HasOrganizationColumn;
use Blafast\Foundation\Events\JobFailed;
use Blafast\Foundation\Exceptions\JsonApiExceptionHandler;
use Blafast\Foundation\Foundation\ModuleManifest;
use Blafast\Foundation\Http\Middleware\AddRateLimitHeaders;
use Blafast\Foundation\Http\Middleware\DeferredRequestMiddleware;
use Blafast\Foundation\Http\Middleware\EnsureOrganizationContext;
use Blafast\Foundation\Http\Middleware\ResolveOrganizationContext;
use Blafast\Foundation\Listeners\InvalidateMetadataCacheOnModelUpdate;
use Blafast\Foundation\Listeners\InvalidateMetadataCacheOnPermissionChange;
use Blafast\Foundation\Listeners\NotifySuperadminsOnJobFailure;
use Blafast\Foundation\Models\Activity;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\Media;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Models\SystemSetting;
use Blafast\Foundation\Policies\ActivityPolicy;
use Blafast\Foundation\Policies\DeferredApiRequestPolicy;
use Blafast\Foundation\Policies\OrganizationPolicy;
use Blafast\Foundation\Policies\SystemSettingPolicy;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Providers\RateLimitServiceProvider;
use Blafast\Foundation\Providers\ResponseMacroServiceProvider;
use Blafast\Foundation\Services\ExecPermissionChecker;
use Blafast\Foundation\Services\FileService;
use Blafast\Foundation\Services\MenuRegistry;
use Blafast\Foundation\Services\MenuService;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelMetaService;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Services\ModuleRegistry;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Services\PaginationService;
use Blafast\Foundation\Services\QueryBuilderService;
use Blafast\Foundation\Services\SettingsService;
use Blafast\Foundation\Tests\Fixtures\AddressableModel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\PermissionRegistrar;

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
            // Only the package's own configs (H21). The old list shipped whole
            // framework/vendor configs (auth, permission, sanctum, queue, …) through
            // mergeConfigFrom — a top-level merge the HOST wins, so the required
            // teams/guard/model settings never took effect in a normally-installed
            // host (spatie silently ran with teams=false: a cross-tenant leak).
            // Required framework settings are now applied imperatively in
            // applyRequiredFrameworkConfig() and verified by a boot-time check.
            ->hasConfigFile(['blafast-fundation', 'jsonapi'])
            ->hasViews()
            ->hasRoute('api')
            // Real timestamped migrations, FK-ordered by filename. discoversMigrations()
            // registers every file in database/migrations with the Migrator AND exposes
            // the `blafast-fundation-migrations` publish tag (the fork path for hosts
            // that want to own the schema — pair publishing with
            // FOUNDATION_RUN_MIGRATIONS=false, or every migration runs twice).
            ->discoversMigrations()
            ->hasCommands([
                BlafastCommand::class,
                MetadataCacheCommand::class,
                CleanupActivityLogCommand::class,
                QueueStatusCommand::class,
                RetryFailedJobsCommand::class,
                ModulesDiscoverCommand::class,
                ModulesListCommand::class,
                PermissionsSyncCommand::class,
                PermissionsMigrateSlugsCommand::class,
                DeferredCleanupCommand::class,
                SchedulerHealthCheckCommand::class,
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

        // Auto-run the package migrations on `php artisan migrate` (default). Hosts
        // that publish the migrations to fork them must disable this via
        // FOUNDATION_RUN_MIGRATIONS=false / run_migrations in the published config,
        // otherwise each migration registers twice. Decided HERE — after the config
        // merge above — so `config()` is authoritative and no env() call is needed;
        // bootPackageMigrations() only reads the flag at boot time, after this runs.
        $this->package->runsMigrations((bool) config('blafast-fundation.run_migrations', true));

        // H21: the settings the package cannot function without, applied imperatively
        // (a config-file merge is won by the host and silently disabled all of them).
        $this->applyRequiredFrameworkConfig();

        // Register OrganizationContext as a scoped singleton (per-request)
        $this->app->scoped(OrganizationContext::class, function () {
            return new OrganizationContext;
        });

        // Register PaginationService as a singleton
        $this->app->singleton(PaginationService::class);

        // Register ModelRegistry as a singleton
        $this->app->singleton(ModelRegistry::class);

        // Register ModelMetaService as a singleton
        $this->app->singleton(ModelMetaService::class);

        // Register MetadataCacheService as a singleton
        $this->app->singleton(MetadataCacheService::class);

        // Register MenuRegistry as a singleton
        $this->app->singleton(MenuRegistry::class);

        // Register MenuService as a singleton
        $this->app->singleton(MenuService::class);

        // Register QueryBuilderService as a singleton
        $this->app->singleton(QueryBuilderService::class);

        // Register FileService as a singleton
        $this->app->singleton(FileService::class);

        // Register SettingsService as a singleton
        $this->app->singleton(SettingsService::class);

        // Register ModuleManifest as a singleton
        $this->app->singleton(ModuleManifest::class, function ($app) {
            return new ModuleManifest(base_path());
        });

        // Register ModuleRegistry as a singleton
        $this->app->singleton(ModuleRegistry::class);

        // Register ExecPermissionChecker as a singleton
        $this->app->singleton(ExecPermissionChecker::class);

        // Register the migration helper for Blueprint macros
        $this->app->register(HasOrganizationColumn::class);
    }

    /**
     * Apply the framework/vendor settings the package cannot function without
     * (H21). Imperative on purpose: registering whole `auth`/`permission`/…
     * config files went through mergeConfigFrom, a top-level merge the host
     * wins — so in a normally-installed host spatie ran with `teams = false`
     * (every org role applied globally: a cross-tenant leak), the wrong
     * Role/Permission models, and no `api` guard. Only the specific required
     * keys are written; everything else the host owns stays untouched.
     */
    private function applyRequiredFrameworkConfig(): void
    {
        // spatie permission — the multi-tenant RBAC contract. events_enabled makes
        // spatie fire its Role/PermissionAttached/Detached class events, which the
        // metadata-cache invalidation listeners depend on (M2).
        config([
            'permission.teams' => true,
            'permission.column_names.team_foreign_key' => 'organization_id',
            'permission.column_names.model_morph_key' => 'model_uuid',
            'permission.models.permission' => Permission::class,
            'permission.models.role' => Role::class,
            'permission.events_enabled' => true,
        ]);

        // activitylog — the package's uuid + organization-scoped Activity model.
        config([
            'activitylog.activity_model' => Activity::class,
            'activitylog.subject_returns_soft_deleted_models' => true,
            'activitylog.default_auth_driver' => 'sanctum',
        ]);

        // medialibrary — the package's uuid + organization-scoped Media model (the
        // schema has a uuid PK, so the vendor model's getKey() would be null and
        // every URL/path generation crashes). A host's own custom model is kept.
        if (in_array(config('media-library.media_model'), [null, \Spatie\MediaLibrary\MediaCollections\Models\Media::class], true)) {
            config(['media-library.media_model' => Media::class]);
        }

        // The `api` guard every package route/permission runs on — created only
        // when absent, pointing at the host's own user provider.
        if (! config('auth.guards.api')) {
            config(['auth.guards.api' => [
                'driver' => 'sanctum',
                'provider' => config('auth.defaults.provider', 'users'),
            ]]);
        }
    }

    /**
     * Boot-time sanity check of the host contract (H21). Public static so hosts
     * (and tests) can invoke it directly, e.g. from a deploy smoke check.
     *
     * @throws \RuntimeException when a required setting is missing or fought back
     */
    public static function assertHostConfiguration(): void
    {
        $guard = config('auth.guards.api');
        $provider = is_array($guard) ? ($guard['provider'] ?? null) : null;

        if (! is_array($guard) || ! is_string($provider) || ! config("auth.providers.{$provider}")) {
            throw new \RuntimeException(
                'blafast-fundation: the [api] auth guard is missing or points at an undefined '
                .'auth provider — every package route and permission runs on it. Define '
                .'auth.guards.api (driver "sanctum") with a valid user provider, or set '
                .'auth.defaults.provider so the package can create the guard itself. '
                .'See docs/HOST-REQUIREMENTS.md.'
            );
        }

        if (config('permission.teams') !== true
            || config('permission.column_names.team_foreign_key') !== 'organization_id') {
            throw new \RuntimeException(
                'blafast-fundation: spatie permission is not in teams mode with '
                .'team_foreign_key = organization_id. The package applies these settings at '
                .'register time; something later in the boot process overrode them — with '
                .'teams off, an organization role would apply to EVERY tenant. '
                .'See docs/HOST-REQUIREMENTS.md.'
            );
        }
    }

    /**
     * Bootstrap any package services.
     */
    public function packageBooted(): void
    {
        // Re-applied at boot (idempotent): anything that rewrote these configs
        // between register and boot — e.g. testbench's environment setup, or a host
        // provider registered after this one — is corrected before the assert.
        $this->applyRequiredFrameworkConfig();

        // Fail loudly on a broken host contract — a silent wrong-guard denial (or a
        // silent teams=false cross-tenant leak) is far worse than a boot error (H21).
        static::assertHostConfiguration();

        // Register middleware aliases
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('org.resolve', ResolveOrganizationContext::class);
        $router->aliasMiddleware('org.required', EnsureOrganizationContext::class);
        $router->aliasMiddleware('rate-limit-headers', AddRateLimitHeaders::class);
        $router->aliasMiddleware('deferred', DeferredRequestMiddleware::class);

        // Register response macros
        $this->app->register(ResponseMacroServiceProvider::class);

        // Register rate limiting
        $this->app->register(RateLimitServiceProvider::class);

        // Register dynamic route macros
        $this->app->register(DynamicRouteServiceProvider::class);

        // Register scheduled tasks
        $this->app->register(ScheduleServiceProvider::class);

        // Register policies
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(DeferredApiRequest::class, DeferredApiRequestPolicy::class);

        // H6: generic authorization for every HasApiStructure model WITHOUT an
        // explicit policy — before this, Gate found no policy for module models
        // registered via Route::dynamicResource() and denied permanently,
        // regardless of grants. Canonical mapping: viewAny → list_{slug},
        // view/create/update/delete → {ability}_{slug}, slug = getApiSlug().
        // Superadmins pass, mirroring ExecPermissionChecker. Explicit policies
        // keep full control (the hook abstains); non-grants fall through to the
        // default denial rather than hard-false, so later Gate definitions can
        // still apply.
        Gate::before(function (object $user, string $ability, array $arguments = []) {
            $target = $arguments[0] ?? null;
            $class = is_object($target)
                ? $target::class
                : (is_string($target) && class_exists($target) ? $target : null);

            if ($class === null || ! is_subclass_of($class, HasApiStructure::class)) {
                return null;
            }

            if (Gate::getPolicyFor($class) !== null) {
                return null;
            }

            if (method_exists($user, 'isSuperadmin') && $user->isSuperadmin()) {
                return true;
            }

            $map = ['viewAny' => 'list', 'view' => 'view', 'create' => 'create', 'update' => 'update', 'delete' => 'delete'];

            if (isset($map[$ability]) && method_exists($user, 'can') && $user->can($map[$ability].'_'.$class::getApiSlug())) {
                return true;
            }

            return null;
        });

        // Register JSON:API exception handler
        $this->registerExceptionHandler();

        // Register morph map for polymorphic relationships
        $morphMap = [
            'organization' => Organization::class,
        ];

        // In testing environment, use test fixtures
        if ($this->app->environment('testing')) {
            if (class_exists(Tests\Fixtures\User::class)) {
                $morphMap['user'] = Tests\Fixtures\User::class;
            }
            if (class_exists(AddressableModel::class)) {
                $morphMap['addressable_model'] = AddressableModel::class;
            }
        } elseif (class_exists(User::class)) {
            // Add User class if it exists in non-testing environment
            $morphMap['user'] = User::class;
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

            $handler->renderable(function (\Throwable $e, Request $request) use ($jsonApiHandler) {
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

        // Listen to spatie's REAL class events (M2): the old string names
        // ('permission.attached', …) are never dispatched by spatie — it fires
        // these event classes, and only when permission.events_enabled is true
        // (set by applyRequiredFrameworkConfig()).
        if (class_exists(PermissionRegistrar::class)) {
            Event::listen(PermissionAttachedEvent::class, InvalidateMetadataCacheOnPermissionChange::class);
            Event::listen(PermissionDetachedEvent::class, InvalidateMetadataCacheOnPermissionChange::class);
            Event::listen(RoleAttachedEvent::class, InvalidateMetadataCacheOnPermissionChange::class);
            Event::listen(RoleDetachedEvent::class, InvalidateMetadataCacheOnPermissionChange::class);
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
