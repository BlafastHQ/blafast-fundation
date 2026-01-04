<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Service for checking exec permissions on model methods.
 *
 * Handles permission resolution for method execution access control,
 * supporting both model-level and method-level permissions.
 */
class ExecPermissionChecker
{
    /**
     * Check if user can execute a method on a model.
     *
     * Resolution order:
     * 1. Superadmin role bypasses all checks
     * 2. Model-level permission (exec.{model-slug})
     * 3. Method-level permission (exec.{model-slug}.{method-slug})
     */
    public function canExecute(
        Authenticatable $user,
        string $modelSlug,
        string $methodSlug
    ): bool {
        // @phpstan-ignore method.notFound
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        if ($user->can("exec.{$modelSlug}")) {
            return true;
        }

        if ($user->can("exec.{$modelSlug}.{$methodSlug}")) {
            return true;
        }

        return false;
    }

    /**
     * Get all executable methods for user on a model.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array<int, string>
     */
    public function executableMethods(
        Authenticatable $user,
        string $modelClass
    ): array {
        if (! method_exists($modelClass, 'apiMethods')) {
            return [];
        }

        $apiMethods = $modelClass::apiMethods();
        $allMethods = array_keys($apiMethods);

        // Get model slug
        $slug = $this->getModelSlug($modelClass);

        // Superadmin or model-level permission = all methods
        // @phpstan-ignore method.notFound
        if ($user->hasRole('Superadmin') || $user->can("exec.{$slug}")) {
            return $allMethods;
        }

        // Check each method
        return array_values(array_filter($allMethods, function ($method) use ($user, $slug) {
            return $user->can("exec.{$slug}.{$method}");
        }));
    }

    /**
     * Get the model slug from a model class.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function getModelSlug(string $modelClass): string
    {
        if (method_exists($modelClass, 'getApiSlug')) {
            return $modelClass::getApiSlug();
        }

        return strtolower(class_basename($modelClass));
    }
}
