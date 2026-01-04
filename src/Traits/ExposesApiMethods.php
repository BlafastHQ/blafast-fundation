<?php

declare(strict_types=1);

namespace Blafast\Foundation\Traits;

use Blafast\Foundation\Dto\ApiMethod;

/**
 * Trait for models that expose callable methods via API.
 *
 * Provides helper methods to access and work with API method
 * definitions declared in the model's apiMethods() method.
 */
trait ExposesApiMethods
{
    /**
     * Get compiled API methods.
     *
     * @return array<string, ApiMethod>
     */
    public static function getApiMethods(): array
    {
        $methods = [];

        foreach (static::apiMethods() as $slug => $config) {
            $methods[$slug] = ApiMethod::fromArray($slug, $config);
        }

        return $methods;
    }

    /**
     * Get a specific API method by slug.
     */
    public static function getApiMethod(string $slug): ?ApiMethod
    {
        $methods = static::apiMethods();

        if (! isset($methods[$slug])) {
            return null;
        }

        return ApiMethod::fromArray($slug, $methods[$slug]);
    }

    /**
     * Check if a method slug is exposed.
     */
    public static function hasApiMethod(string $slug): bool
    {
        return isset(static::apiMethods()[$slug]);
    }

    /**
     * Get method slugs for a given HTTP method.
     *
     * @return array<int, string>
     */
    public static function getApiMethodsByHttpMethod(string $httpMethod): array
    {
        $httpMethod = strtoupper($httpMethod);

        return collect(static::apiMethods())
            ->filter(fn ($config) => strtoupper($config['http_method'] ?? 'POST') === $httpMethod)
            ->keys()
            ->toArray();
    }

    /**
     * Get all method slugs.
     *
     * @return array<int, string>
     */
    public static function getApiMethodSlugs(): array
    {
        return array_keys(static::apiMethods());
    }

    /**
     * Get exec permissions for a role.
     *
     * @return array<int, string>
     */
    public static function getExecPermissionsForRole(string $role): array
    {
        $rights = static::defaultRights();
        $exec = $rights['exec'] ?? [];

        return $exec[$role] ?? [];
    }
}
