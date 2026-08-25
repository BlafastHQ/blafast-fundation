<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Settings management service with precedence resolution.
 *
 * Provides access to system-wide and organization-specific settings
 * with proper precedence (organization overrides system).
 */
class SettingsService
{
    private const SYSTEM_CACHE_KEY = 'settings:system';

    private const SYSTEM_PUBLIC_CACHE_KEY = 'settings:system:public';

    private const ORG_CACHE_PREFIX = 'settings:organization-';

    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Create a new settings service instance.
     */
    public function __construct(
        private readonly OrganizationContext $context,
    ) {}

    /**
     * Get a setting value with precedence resolution.
     *
     * Returns an array with the value and the source ('organization', 'system', or 'default').
     * Organization settings take precedence over system settings.
     *
     * @return array{value: mixed, source: string}
     */
    public function get(string $key, mixed $default = null): array
    {
        // Check organization settings first (highest precedence)
        if ($this->context->hasContext()) {
            $orgSettings = $this->getOrganizationSettings();
            if (Arr::has($orgSettings, $key)) {
                return [
                    'value' => Arr::get($orgSettings, $key),
                    'source' => 'organization',
                ];
            }
        }

        // Fall back to system settings
        $systemSettings = $this->getSystemSettings();
        if (isset($systemSettings[$key])) {
            return [
                'value' => $systemSettings[$key],
                'source' => 'system',
            ];
        }

        // Return default value
        return [
            'value' => $default,
            'source' => 'default',
        ];
    }

    /**
     * Get just the setting value without source information.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default)['value'];
    }

    /**
     * Set a system setting. `is_public`, `group` and `description` are persisted
     * when provided (H4: the controller used to validate and silently drop them).
     */
    public function setSystem(
        string $key,
        mixed $value,
        ?string $type = null,
        ?bool $isPublic = null,
        ?string $group = null,
        ?string $description = null,
    ): void {
        $setting = SystemSetting::firstOrNew(['key' => $key]);

        if ($type) {
            $setting->type = $type;
        }

        if ($isPublic !== null) {
            $setting->is_public = $isPublic;
        }

        if ($group !== null) {
            $setting->group = $group;
        }

        if ($description !== null) {
            $setting->description = $description;
        }

        $setting->setTypedValue($value)->save();

        $this->invalidateSystemCache();
    }

    /**
     * Set an organization setting.
     */
    public function setOrganization(string $key, mixed $value): void
    {
        $this->setOrganizationMany([$key => $value]);
    }

    /**
     * Set several organization settings atomically (M4). The old path saved the
     * context's in-memory organization — a whole-JSON read-modify-write from a
     * snapshot taken at middleware time, so concurrent writers erased each
     * other's keys. Lock and RE-READ the row inside a transaction instead.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setOrganizationMany(array $settings): void
    {
        $org = $this->context->organization();

        if (! $org) {
            throw new \RuntimeException('No organization context');
        }

        DB::transaction(function () use ($org, $settings) {
            /** @var Organization $fresh */
            $fresh = $org->newQuery()->lockForUpdate()->findOrFail($org->id);

            foreach ($settings as $key => $value) {
                $fresh->setSetting($key, $value);
            }

            $fresh->save();

            // Keep the context's instance coherent with what was just persisted.
            $org->setRawAttributes($fresh->getAttributes(), true);
        });

        $this->invalidateOrganizationCache($org->id);
    }

    /**
     * Get all system settings (cached).
     *
     * @return array<string, mixed>
     */
    public function getSystemSettings(): array
    {
        return Cache::remember(
            self::SYSTEM_CACHE_KEY,
            self::CACHE_TTL,
            function () {
                return SystemSetting::all()
                    ->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()])
                    ->toArray();
            }
        );
    }

    /**
     * Get organization settings (cached).
     *
     * @return array<string, mixed>
     */
    public function getOrganizationSettings(): array
    {
        $orgId = $this->context->id();

        if (! $orgId) {
            return [];
        }

        return Cache::remember(
            self::ORG_CACHE_PREFIX.$orgId,
            self::CACHE_TTL,
            function () {
                $org = $this->context->organization();

                return $org && $org->settings ? (array) $org->settings : [];
            }
        );
    }

    /**
     * Invalidate system settings cache.
     */
    public function invalidateSystemCache(): void
    {
        Cache::forget(self::SYSTEM_CACHE_KEY);
        Cache::forget(self::SYSTEM_PUBLIC_CACHE_KEY);
    }

    /**
     * Invalidate organization settings cache.
     */
    public function invalidateOrganizationCache(string $organizationId): void
    {
        Cache::forget(self::ORG_CACHE_PREFIX.$organizationId);
    }

    /**
     * Get only the PUBLIC system settings (cached separately). The resolved
     * endpoint must never expose `is_public = false` rows to plain members (H4).
     *
     * @return array<string, mixed>
     */
    public function getPublicSystemSettings(): array
    {
        return Cache::remember(
            self::SYSTEM_PUBLIC_CACHE_KEY,
            self::CACHE_TTL,
            function () {
                return SystemSetting::query()->public()
                    ->get()
                    ->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()])
                    ->toArray();
            }
        );
    }

    /**
     * Get all settings for the current context, resolved per dotted key exactly
     * as get() resolves (M3): organization values win, system fills the rest.
     * Org settings are stored nested (data_set) while system keys are flat
     * strings, so the org tree is flattened to dotted keys before merging —
     * the old shallow array_merge let a nested org subtree shadow unrelated
     * flat system keys and disagreed with per-key get().
     *
     * @param  bool  $publicSystemOnly  restrict the system tier to `is_public`
     *                                  rows (the resolved endpoint for non-superadmins)
     * @return array<string, mixed>
     */
    public function all(bool $publicSystemOnly = false): array
    {
        $system = $publicSystemOnly ? $this->getPublicSystemSettings() : $this->getSystemSettings();
        $org = Arr::dot($this->getOrganizationSettings());

        return array_merge($system, $org);
    }
}
