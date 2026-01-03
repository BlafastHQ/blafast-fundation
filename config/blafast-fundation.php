<?php

declare(strict_types=1);

// Configuration for Blafast Foundation Module
return [

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API behavior including versioning, pagination, and error handling.
    |
    */
    'api' => [
        // API version prefix (e.g., /api/v1/...)
        'version' => env('BLAFAST_API_VERSION', 'v1'),

        // Default pagination settings
        'pagination' => [
            'default_per_page' => 25,
            'max_per_page' => 100,
            // Use cursor pagination by default
            'type' => 'cursor',
            // Query parameter names
            'cursor_name' => 'cursor',
            'size_name' => 'per_page',
        ],

        // Rate limiting configuration
        'rate_limiting' => [
            // Authentication routes (login, register, password reset)
            'auth' => [
                'max_attempts' => 60,
                'decay_minutes' => 1,
            ],
            // Data API routes
            'api' => [
                'max_attempts' => 300,
                'decay_minutes' => 1,
            ],
            // Exempt superadmins from rate limiting
            'exempt_superadmins' => true,
        ],

        // Error handling
        'errors' => [
            // Return JSON:API error objects
            'format' => 'jsonapi',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel Sanctum authentication.
    |
    */
    'auth' => [
        // Sanctum guard name
        'guard' => 'api',

        // Token settings
        'token' => [
            // Token expiration in minutes (null = never expires)
            'expiration' => env('BLAFAST_TOKEN_EXPIRATION', null),
            // Token name for SPA authentication
            'name' => 'api-token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization Configuration
    |--------------------------------------------------------------------------
    |
    | Multi-tenancy settings for organization-based data isolation.
    |
    */
    'organization' => [
        // Organization context header name
        'header_name' => 'X-Organization-Id',

        // Fallback to session for SPA convenience
        'session_fallback' => true,

        // Session key for storing last used organization
        'session_key' => 'blafast.organization_id',

        // Require organization context for all users except superadmins
        'require_context' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles and Permissions
    |--------------------------------------------------------------------------
    |
    | Configuration for spatie/laravel-permission package.
    |
    */
    'permissions' => [
        // Enable teams mode for organization-scoped permissions
        'teams' => true,

        // Team foreign key
        'team_foreign_key' => 'organization_id',

        // Global roles (not scoped to organizations)
        'global_roles' => [
            'Superadmin',
        ],

        // Organization-level default roles
        'organization_roles' => [
            'Admin',
            'User',
            'Viewer',
            'Consumer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching metadata, menus, and settings.
    |
    */
    'cache' => [
        // Enable caching
        'enabled' => env('BLAFAST_CACHE_ENABLED', true),

        // Default TTL for metadata responses (in seconds)
        'metadata_ttl' => env('BLAFAST_CACHE_METADATA_TTL', 600), // 10 minutes

        // Menu cache TTL (in seconds)
        'menu_ttl' => env('BLAFAST_CACHE_MENU_TTL', 600), // 10 minutes

        // Settings cache TTL (in seconds)
        'settings_ttl' => env('BLAFAST_CACHE_SETTINGS_TTL', 600), // 10 minutes

        // Cache driver (null = use default)
        'driver' => env('BLAFAST_CACHE_DRIVER', null),

        // Cache key prefix
        'prefix' => 'blafast',

        // Enable cache tagging (requires Redis or Memcached)
        'tagging' => env('BLAFAST_CACHE_TAGGING', false),

        // Enable cache monitoring (fires events on cache misses)
        'monitoring_enabled' => env('BLAFAST_CACHE_MONITORING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Job queue settings for background processing.
    |
    */
    'queue' => [
        // Default queue connection
        'connection' => env('BLAFAST_QUEUE_CONNECTION', 'database'),

        // Queue names for different job types
        'names' => [
            'default' => 'default',
            'notifications' => 'notifications',
            'media' => 'media',
            'exports' => 'exports',
            'deferred' => 'deferred',
            'deferred_high' => 'deferred-high',
            'deferred_low' => 'deferred-low',
        ],

        // Failed job configuration
        'failed' => [
            'notify_superadmins' => env('BLAFAST_QUEUE_NOTIFY_SUPERADMINS', true),
            'notify_after_attempts' => 3,
        ],

        // Timeout configuration for different queue types (in seconds)
        'timeouts' => [
            'default' => 60,
            'notifications' => 60,
            'media' => 300,
            'exports' => 600,
            'deferred' => 300,
            'deferred_high' => 300,
            'deferred_low' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for spatie/laravel-activitylog package.
    |
    */
    'activity_log' => [
        // Enable activity logging
        'enabled' => env('BLAFAST_ACTIVITY_LOG_ENABLED', true),

        // Log retention in days (0 = keep forever)
        'retention_days' => 90,

        // Automatically log model events
        'log_events' => [
            'created',
            'updated',
            'deleted',
        ],

        // Include organization_id in activity log
        'include_organization' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for automatic API structure discovery.
    |
    */
    'discovery' => [
        // Enable automatic model discovery
        'enabled' => true,

        // Default search strategy
        'search_strategy' => 'like', // Options: 'like', 'full_text', 'none'

        // Cache discovery results
        'cache_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Configuration
    |--------------------------------------------------------------------------
    |
    | Dynamic menu system settings.
    |
    */
    'menu' => [
        // Enable menu caching
        'cache_enabled' => true,

        // Default menu order for items
        'default_order' => 100,

        // Cache TTL in seconds
        'cache_ttl' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Library Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for spatie/laravel-medialibrary package.
    |
    */
    'media' => [
        // Default disk for media storage
        'disk' => env('BLAFAST_MEDIA_DISK', 'public'),

        // Default max file size in KB
        'max_file_size' => 10240, // 10MB

        // Enable responsive images
        'responsive_images' => true,

        // Queue media conversions
        'queue_conversions' => true,

        // Image conversion presets
        'conversions' => [
            'thumb' => [
                'width' => 150,
                'height' => 150,
                'quality' => 80,
                'format' => 'webp',
            ],
            'preview' => [
                'width' => 800,
                'height' => 600,
                'quality' => 85,
                'format' => 'webp',
            ],
            'large' => [
                'width' => 1920,
                'height' => 1080,
                'quality' => 90,
                'format' => 'webp',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred API Requests Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for asynchronous API request processing.
    |
    */
    'deferred' => [
        // Enable deferred request system
        'enabled' => env('BLAFAST_DEFERRED_ENABLED', true),

        // Default timeout for deferred jobs (in seconds)
        'timeout' => 300, // 5 minutes

        // Default result TTL (in seconds)
        'result_ttl' => 3600, // 1 hour

        // Default priority
        'priority' => 'default',

        // Cleanup configuration
        'cleanup' => [
            'enabled' => true,
            'older_than_days' => 7,
        ],

        // Request header name for opt-in deferred execution
        'header_name' => 'X-Blafast-Defer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for loading and discovering Laravel modules.
    |
    */
    'modules' => [
        // Enable module auto-discovery
        'auto_discover' => true,

        // Cache module manifest
        'cache_enabled' => true,

        // Module manifest cache file
        'manifest_cache' => storage_path('framework/cache/blafast-modules.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Configuration
    |--------------------------------------------------------------------------
    |
    | Locale and currency settings.
    |
    */
    'localization' => [
        // Default locale
        'default_locale' => env('BLAFAST_DEFAULT_LOCALE', 'en'),

        // Default currency
        'default_currency' => env('BLAFAST_DEFAULT_CURRENCY', 'USD'),

        // Available locales
        'available_locales' => ['en', 'fr', 'de', 'es'],
    ],

    /*
    |--------------------------------------------------------------------------
    | PEPPOL Configuration
    |--------------------------------------------------------------------------
    |
    | Electronic invoicing settings for PEPPOL network.
    |
    */
    'peppol' => [
        // Enable PEPPOL integration
        'enabled' => env('BLAFAST_PEPPOL_ENABLED', false),

        // PEPPOL endpoint configuration
        'endpoint' => env('BLAFAST_PEPPOL_ENDPOINT', null),
    ],

];
