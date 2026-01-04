<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Settings management service with precedence resolution.
 *
 * Provides access to system-wide and organization-specific settings
 * with proper precedence (organization overrides system).
 */
class SettingsService
{
    private const SYSTEM_CACHE_KEY = 'settings:system';

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
     * Set a system setting.
     */
    public function setSystem(string $key, mixed $value, ?string $type = null): void
    {
        $setting = SystemSetting::firstOrNew(['key' => $key]);

        if ($type) {
            $setting->type = $type;
        }

        $setting->setTypedValue($value)->save();

        $this->invalidateSystemCache();
    }

    /**
     * Set an organization setting.
     */
    public function setOrganization(string $key, mixed $value): void
    {
        $org = $this->context->organization();

        if (! $org) {
            throw new \RuntimeException('No organization context');
        }

        $org->setSetting($key, $value)->save();

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
    }

    /**
     * Invalidate organization settings cache.
     */
    public function invalidateOrganizationCache(string $organizationId): void
    {
        Cache::forget(self::ORG_CACHE_PREFIX.$organizationId);
    }

    /**
     * Get all settings for the current context.
     *
     * Merges system and organization settings with organization taking precedence.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $system = $this->getSystemSettings();
        $org = $this->getOrganizationSettings();

        // Merge with organization settings taking precedence
        return array_merge($system, $org);
    }
}
