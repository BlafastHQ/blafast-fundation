<?php

declare(strict_types=1);

namespace Blafast\Foundation\Contracts;

/**
 * Interface for models that expose callable methods via API.
 *
 * Models implementing this interface can define methods that
 * can be called through the API with parameter validation
 * and permission checking.
 */
interface HasApiMethods
{
    /**
     * Get the methods exposed via API.
     *
     * @return array<string, array{
     *     method: string,
     *     http_method: string,
     *     description: string,
     *     parameters?: array<string, array>,
     *     returns?: array,
     *     queued?: bool
     * }>
     */
    public static function apiMethods(): array;

    /**
     * Get default permission assignments for roles.
     *
     * @return array{
     *     view?: array<string>,
     *     create?: array<string>,
     *     update?: array<string>,
     *     delete?: array<string>,
     *     exec?: array<string, array<string>>
     * }
     */
    public static function defaultRights(): array;
}
