<?php

declare(strict_types=1);

namespace Blafast\Foundation\Api;

/**
 * The single normalizer for raw `apiMethods()` declarations (task 27 / M15).
 *
 * The documented plain-list pattern
 * (`[ApiMethodBuilder::make('send', …)->build()]`) used to yield numeric array
 * keys, and every consumer keyed methods by array key: `/meta` advertised
 * `slug => 0`, permission sync created `exec.{model}.0`, and `call/send`
 * 404'd — the whole RPC surface was unreachable unless the developer hand-keyed
 * the array. Every consumer now routes through this normalizer, keyed by
 * `$config['slug'] ?? $key`, so array position is significant nowhere.
 */
final class ApiMethodNormalizer
{
    /**
     * Normalize a raw apiMethods() array to slug-keyed configs.
     *
     * @param  array<int|string, array<string, mixed>>  $apiMethods
     * @return array<string, array<string, mixed>>
     */
    public static function normalize(array $apiMethods): array
    {
        $normalized = [];

        foreach ($apiMethods as $key => $config) {
            $slug = (string) ($config['slug'] ?? $key);
            $config['slug'] = $slug;
            $normalized[$slug] = $config;
        }

        return $normalized;
    }

    /**
     * Normalize the given model's apiMethods() declaration.
     *
     * @param  class-string  $modelClass
     * @return array<string, array<string, mixed>>
     */
    public static function for(string $modelClass): array
    {
        return self::normalize($modelClass::apiMethods());
    }
}
