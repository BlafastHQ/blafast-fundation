<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Dto\MenuItem;
use Blafast\Foundation\Http\Resources\MenuItemResource;
use Blafast\Foundation\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for user menu endpoint.
 *
 * Returns the hierarchical menu structure authorized for the
 * authenticated user, with permission-based filtering.
 */
class MenuController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private MenuService $menuService,
    ) {}

    /**
     * Get user-specific menu.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Return empty menu for unauthenticated users
        if (! $user) {
            return response()->json([
                'data' => [],
                'included' => [],
            ], 200);
        }

        // Get filtered menu for user
        $menu = $this->menuService->getForUser($user);

        // Build response with included resources
        return response()->json([
            'data' => MenuItemResource::collection($menu),
            'included' => $this->buildIncluded($menu),
        ]);
    }

    /**
     * Build included resources for nested items.
     *
     * @param  array<MenuItem>  $items
     * @return array<array<string, mixed>>
     */
    protected function buildIncluded(array $items): array
    {
        $included = [];

        foreach ($items as $item) {
            foreach ($item->children as $child) {
                $resource = new MenuItemResource($child);
                $included[] = $resource->toArray(request());

                // Recursively include deeper children
                $included = array_merge(
                    $included,
                    $this->buildIncluded([$child])
                );
            }
        }

        return $included;
    }
}
