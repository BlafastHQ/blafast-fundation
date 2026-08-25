<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Http\Requests\UpdateOrganizationSettingsRequest;
use Blafast\Foundation\Http\Requests\UpdateSystemSettingRequest;
use Blafast\Foundation\Http\Resources\SystemSettingResource;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\SystemSetting;
use Blafast\Foundation\Services\SettingsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for settings management API endpoints.
 *
 * Provides CRUD operations for system settings (Superadmin only)
 * and organization settings (Admin only).
 */
class SettingsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Get all system settings (Superadmin only).
     */
    public function systemIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SystemSetting::class);

        $query = SystemSetting::query();

        // Filter by group if specified
        if ($group = $request->input('filter.group')) {
            $query->inGroup($group);
        }

        $settings = $query->get();

        return response()->json([
            'data' => SystemSettingResource::collection($settings),
        ]);
    }

    /**
     * Update or create a system setting (Superadmin only).
     */
    public function systemUpdate(UpdateSystemSettingRequest $request, string $key): JsonResponse
    {
        $this->authorize('update', SystemSetting::class);

        $this->settings->setSystem(
            $key,
            $request->input('value'),
            $request->input('type'),
            $request->has('is_public') ? $request->boolean('is_public') : null,
            $request->input('group'),
            $request->input('description'),
        );

        $setting = SystemSetting::where('key', $key)->firstOrFail();

        return response()->json([
            'data' => new SystemSettingResource($setting),
        ]);
    }

    /**
     * Delete a system setting (Superadmin only).
     */
    public function systemDelete(Request $request, string $key): JsonResponse
    {
        $this->authorize('delete', SystemSetting::class);

        $setting = SystemSetting::where('key', $key)->firstOrFail();
        $setting->delete();

        $this->settings->invalidateSystemCache();

        return response()->json(null, 204);
    }

    /**
     * Get organization settings (Admin only).
     */
    public function organizationIndex(Request $request): JsonResponse
    {
        // H5: the old `manage` ability existed nowhere (no policy method, no gate
        // definition) — Laravel denied by default for EVERYONE, superadmins included.
        $this->authorize('manageSettings', Organization::class);

        $settings = $this->settings->getOrganizationSettings();

        return response()->json([
            'data' => [
                'type' => 'organization-settings',
                'id' => organization_id(),
                'attributes' => $settings,
            ],
        ]);
    }

    /**
     * Update organization settings (Admin only).
     */
    public function organizationUpdate(UpdateOrganizationSettingsRequest $request): JsonResponse
    {
        $this->authorize('manageSettings', Organization::class);

        // One atomic lock+re-read write for the whole batch (M4).
        $this->settings->setOrganizationMany($request->input('settings', []));

        return response()->json([
            'data' => [
                'type' => 'organization-settings',
                'id' => organization_id(),
                'attributes' => $this->settings->getOrganizationSettings(),
            ],
        ]);
    }

    /**
     * Get resolved settings (what the current context sees).
     *
     * Returns merged system and organization settings with proper precedence.
     */
    public function resolved(Request $request): JsonResponse
    {
        // H4: plain members only see PUBLIC system settings here; superadmins keep
        // the full set (they can read everything via systemIndex anyway).
        $user = $request->user();
        $publicOnly = ! ($user && method_exists($user, 'isSuperadmin') && $user->isSuperadmin());

        return response()->json([
            'data' => [
                'type' => 'resolved-settings',
                'id' => organization_id() ?? 'system',
                'attributes' => $this->settings->all(publicSystemOnly: $publicOnly),
            ],
        ]);
    }
}
