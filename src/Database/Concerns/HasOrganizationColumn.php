<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use Illuminate\Support\ServiceProvider;

/**
 * HasOrganizationColumn Concern
 *
 * Provides a Blueprint macro for consistently adding organization_id columns to migrations.
 */
class HasOrganizationColumn extends ServiceProvider
{
    /**
     * Register the blueprint macros.
     */
    public function register(): void
    {
        /**
         * Add an organization_id column with foreign key constraint.
         *
         * @param  bool  $nullable  Whether the organization_id can be null
         * @param  string  $onDelete  What to do on organization delete (cascade, restrict, set null)
         * @return Fluent
         */
        Blueprint::macro('organizationId', function (
            bool $nullable = false,
            string $onDelete = 'cascade'
        ): Fluent {
            /** @var Blueprint $this */
            $column = $this->foreignUuid('organization_id');

            if ($nullable) {
                $column->nullable();
            }

            $column->constrained('organizations')->onDelete($onDelete);

            return $column;
        });

        /**
         * Add an indexed organization_id column without foreign key constraint.
         * Useful when you need the column but don't want the foreign key constraint.
         *
         * @param  bool  $nullable  Whether the organization_id can be null
         * @return Fluent
         */
        Blueprint::macro('organizationIdIndex', function (bool $nullable = false): Fluent {
            /** @var Blueprint $this */
            $column = $this->uuid('organization_id');

            if ($nullable) {
                $column->nullable();
            }

            $column->index();

            return $column;
        });
    }
}
