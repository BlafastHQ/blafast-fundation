<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PaginationService
{
    /**
     * Paginate the given query using cursor pagination.
     */
    public function paginate(
        Builder $query,
        Request $request,
        ?array $apiStructure = null
    ): CursorPaginator {
        $perPage = $this->resolvePerPage($request, $apiStructure);
        $cursorName = config('blafast-fundation.api.pagination.cursor_name', 'cursor');

        return $query->cursorPaginate(
            $perPage,
            ['*'],
            $cursorName,
            $request->input("page.{$cursorName}")
        );
    }

    /**
     * Format the paginated response according to JSON:API specification.
     *
     * @return array<string, mixed>
     */
    public function formatResponse(
        CursorPaginator $paginator,
        callable $transformer
    ): array {
        return [
            'data' => collect($paginator->items())->map($transformer)->values()->all(),
            'links' => $this->buildLinks($paginator),
            'meta' => $this->buildMeta($paginator),
        ];
    }

    /**
     * Resolve the per-page value from request and configuration.
     */
    private function resolvePerPage(Request $request, ?array $apiStructure): int
    {
        $sizeName = config('blafast-fundation.api.pagination.size_name', 'per_page');
        $requested = (int) $request->input("page.{$sizeName}", 0);
        $default = config('blafast-fundation.api.pagination.default_per_page', 25);

        // Check if the model has a custom max size in its apiStructure
        $max = $apiStructure['pagination']['max_size'] ?? null;

        // Fall back to global config max
        if ($max === null) {
            $max = config('blafast-fundation.api.pagination.max_per_page', 100);
        }

        // If no specific size requested, use default
        if ($requested <= 0) {
            return $default;
        }

        // Cap the requested size at the maximum
        return min($requested, $max);
    }

    /**
     * Build JSON:API compliant links object.
     *
     * @return array<string, string|null>
     */
    private function buildLinks(CursorPaginator $paginator): array
    {
        return [
            'first' => $paginator->url(null),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * Build JSON:API compliant meta object.
     *
     * @return array<string, mixed>
     */
    private function buildMeta(CursorPaginator $paginator): array
    {
        return [
            'page' => [
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
