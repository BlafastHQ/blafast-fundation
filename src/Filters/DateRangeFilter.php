<?php

declare(strict_types=1);

namespace Blafast\Foundation\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Custom filter for handling date range queries.
 *
 * Supports:
 * - Single date: filter[created_at]=2024-01-01
 * - From date: filter[created_at][from]=2024-01-01
 * - To date: filter[created_at][to]=2024-12-31
 * - Range: filter[created_at][from]=2024-01-01&filter[created_at][to]=2024-12-31
 *
 * @implements Filter<\Illuminate\Database\Eloquent\Model>
 */
class DateRangeFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (is_array($value)) {
            if (isset($value['from'])) {
                $query->where($property, '>=', $value['from']);
            }
            if (isset($value['to'])) {
                $query->where($property, '<=', $value['to']);
            }
        } else {
            // Single date value - match the exact date
            $query->whereDate($property, $value);
        }
    }
}
