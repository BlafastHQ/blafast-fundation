<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

        // H14: cursor pagination over a non-unique ordering silently loses rows —
        // Laravel only auto-adds a key order when there is NO order by at all, so
        // `?sort=name` over 25 identical names made links.next resolve to an empty
        // page after the first. Append a deterministic primary-key tiebreaker
        // matching the last sort's direction.
        $this->appendUniqueTiebreaker($query);

        // The cursor URL parameter is nested (`page[cursor]`, the documented
        // contract this service also READS) — passing the bare name made
        // links.next emit `?cursor=…`, which the read side then ignored, so the
        // emitted links never round-tripped (H14).
        return $query->cursorPaginate(
            $perPage,
            ['*'],
            "page[{$cursorName}]",
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
     * Append a unique primary-key order as a cursor tiebreaker (H14), unless the
     * query is already ordered by the key.
     *
     * @param  Builder<Model>  $query
     */
    private function appendUniqueTiebreaker(Builder $query): void
    {
        $model = $query->getModel();
        $keyName = $model->getKeyName();
        $qualified = $model->getQualifiedKeyName();

        $orders = $query->getQuery()->orders ?? [];

        foreach ($orders as $order) {
            if (in_array($order['column'] ?? null, [$keyName, $qualified], true)) {
                return;
            }
        }

        $direction = $orders === [] ? 'asc' : ($orders[array_key_last($orders)]['direction'] ?? 'asc');

        $query->orderBy($qualified, $direction);
    }

    /**
     * Resolve the per-page value from request and configuration.
     */
    private function resolvePerPage(Request $request, ?array $apiStructure): int
    {
        $sizeName = config('blafast-fundation.api.pagination.size_name', 'per_page');
        $requested = (int) $request->input("page.{$sizeName}", 0);

        // M12: the model's own `pagination(default: …)` wins over the global
        // config default — the knob existed, /meta advertised it, and this
        // method ignored it.
        $default = $apiStructure['pagination']['default_size']
            ?? config('blafast-fundation.api.pagination.default_per_page', 25);

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
