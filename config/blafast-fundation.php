<?php

declare(strict_types=1);

// Every key in this file is CONSUMED by the package (task 23 removed ~50 dead
// keys — see CHANGELOG). If you add a key, wire it to a consumer in the same
// commit; the config-hygiene test sweep fails on unread keys.
return [

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | When true (default), the package's migrations run automatically with
    | `php artisan migrate`. Set to false when you publish the migrations to
    | fork them (`php artisan vendor:publish --tag=blafast-fundation-migrations`)
    | — leaving auto-run enabled after publishing registers every migration
    | twice and `migrate` fails on "already exists".
    |
    */

    'run_migrations' => env('FOUNDATION_RUN_MIGRATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | JSON:API error rendering scope
    |--------------------------------------------------------------------------
    |
    | 'package' (default): the JSON:API error renderer applies only to requests
    | routed to this package's controllers (or asking for
    | application/vnd.api+json) — your app's own JSON error contract is left
    | untouched. 'all': render EVERY api/JSON error in the JSON:API shape (the
    | pre-1.0 behaviour; opt-in).
    |
    */

    'api_errors' => [
        'scope' => env('FOUNDATION_JSON_API_ERRORS', 'package'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API pagination & rate limiting
    |--------------------------------------------------------------------------
    */

    'api' => [
        'pagination' => [
            // Cursor pagination parameter names: page[{size_name}], page[{cursor_name}]
            'default_per_page' => env('BLAFAST_API_DEFAULT_PER_PAGE', 25),
            'max_per_page' => env('BLAFAST_API_MAX_PER_PAGE', 100),
            'cursor_name' => 'cursor',
            'size_name' => 'per_page',
        ],

        'rate_limiting' => [
            // Requests per minute (the limiters are fixed per-minute windows).
            'auth' => ['max_attempts' => env('BLAFAST_RATE_LIMIT_AUTH', 60)],
            'api' => ['max_attempts' => env('BLAFAST_RATE_LIMIT_API', 300)],
            'exempt_superadmins' => env('BLAFAST_RATE_LIMIT_EXEMPT_SUPERADMINS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication tokens
    |--------------------------------------------------------------------------
    |
    | Token lifetime in MINUTES for tokens issued by the package's auth
    | endpoints (login + token create without an explicit expires_at). Null
    | defers entirely to the host's sanctum.expiration.
    |
    */

    'auth' => [
        'token' => [
            'expiration' => env('BLAFAST_TOKEN_EXPIRATION'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization context resolution
    |--------------------------------------------------------------------------
    */

    'organization' => [
        'header_name' => env('BLAFAST_ORG_HEADER', 'X-Organization-Id'),
        'session_fallback' => env('BLAFAST_ORG_SESSION_FALLBACK', true),
        'session_key' => 'organization_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata / menu / settings caching
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('BLAFAST_CACHE_ENABLED', true),
        'metadata_ttl' => env('BLAFAST_CACHE_METADATA_TTL', 600),
        'menu_ttl' => env('BLAFAST_CACHE_MENU_TTL', 600),
        'settings_ttl' => env('BLAFAST_CACHE_SETTINGS_TTL', 600),
        'monitoring_enabled' => env('BLAFAST_CACHE_MONITORING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'names' => [
            'default' => 'default',
            'notifications' => 'notifications',
            'media' => 'media',
            'exports' => 'exports',
            'deferred' => 'deferred',
            'deferred_high' => 'deferred-high',
            'deferred_low' => 'deferred-low',
        ],

        'failed' => [
            'notify_superadmins' => env('BLAFAST_QUEUE_NOTIFY_SUPERADMINS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity log
    |--------------------------------------------------------------------------
    */

    'activity_log' => [
        // Default retention for blafast:activity:cleanup (overridable via --days).
        'retention_days' => env('BLAFAST_ACTIVITY_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    'media' => [
        // Default upload disk (task 24): private by default — the provider ships
        // a `blafast-private` local disk (visibility private, temporary URLs).
        // Collections opt into a public disk explicitly via ->useDisk().
        'disk' => env('BLAFAST_MEDIA_DISK', 'blafast-private'),

        // Maximum upload size in bytes.
        'max_file_size' => env('BLAFAST_MEDIA_MAX_FILE_SIZE', 10 * 1024 * 1024),

        // Wired by task 25 (queued conversions).
        'queue_conversions' => env('BLAFAST_MEDIA_QUEUE_CONVERSIONS', true),

        'conversions' => [
            'thumb' => ['width' => 150, 'height' => 150, 'quality' => 80, 'format' => 'webp'],
            'preview' => ['width' => 500, 'height' => 500, 'quality' => 85, 'format' => 'webp'],
            'large' => ['width' => 1200, 'height' => 1200, 'quality' => 90, 'format' => 'webp'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred API requests
    |--------------------------------------------------------------------------
    */

    'deferred' => [
        'enabled' => env('BLAFAST_DEFERRED_ENABLED', true),

        // Job timeout in seconds.
        'timeout' => env('BLAFAST_DEFERRED_TIMEOUT', 300),

        // Default result TTL in seconds (a DeferredEndpointConfig row overrides).
        'result_ttl' => env('BLAFAST_DEFERRED_RESULT_TTL', 3600),

        // Default priority when the endpoint config declares none.
        'priority' => 'default',

        'cleanup' => [
            'enabled' => true,
            'older_than_days' => env('BLAFAST_DEFERRED_CLEANUP_DAYS', 7),
        ],

        // Request header name for opt-in deferred execution.
        'header_name' => 'X-Blafast-Defer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    'modules' => [
        // Manifest cache file for discovered modules.
        'manifest_cache' => env('BLAFAST_MODULES_MANIFEST', base_path('bootstrap/cache/blafast-modules.php')),
    ],
];
