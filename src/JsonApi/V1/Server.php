<?php

declare(strict_types=1);

namespace Blafast\Foundation\JsonApi\V1;

use LaravelJsonApi\Core\Server\Server as BaseServer;

/**
 * JSON:API Server (Version 1)
 *
 * Configures the JSON:API server for API version 1.
 */
class Server extends BaseServer
{
    /**
     * The base URI namespace for this server.
     *
     * @var string
     */
    protected string $baseUri = '/api/v1';

    /**
     * Bootstrap the server when it is handling an HTTP request.
     */
    public function serving(): void
    {
        // Additional server bootstrapping can be done here
        // For example: setting default includes, field sets, etc.
    }

    /**
     * Get the server's list of schemas.
     *
     * @return array<int, class-string>
     */
    protected function allSchemas(): array
    {
        return [
            // Schemas will be registered here as they are created
            // Example: Schemas\OrganizationSchema::class,
        ];
    }
}
