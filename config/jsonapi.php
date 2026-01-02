<?php

declare(strict_types=1);

use Blafast\Foundation\JsonApi\V1\Server as V1Server;

return [

    /*
    |--------------------------------------------------------------------------
    | JSON:API Servers
    |--------------------------------------------------------------------------
    |
    | Configure the JSON:API servers for your application. Each server
    | represents a different API version.
    |
    */

    'servers' => [
        'v1' => V1Server::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware that will be applied to JSON:API routes. This can be
    | overridden per-server if needed.
    |
    */

    'middleware' => [
        'api',
        'auth:sanctum',
        'org.resolve',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Negotiation
    |--------------------------------------------------------------------------
    |
    | Configure how the server handles content negotiation. If set to true,
    | the server will validate the Accept and Content-Type headers.
    |
    */

    'content_negotiation' => true,

    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    |
    | Configure exception rendering for JSON:API errors.
    |
    */

    'exceptions' => [
        'debug' => config('app.debug', false),
        'report' => [
            // Exception classes that should be reported
        ],
        'dont_report' => [
            // Exception classes that should not be reported
            \LaravelJsonApi\Core\Exceptions\JsonApiException::class,
        ],
    ],

];
