<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Seeder for default system settings.
 *
 * Creates default system-wide configuration settings
 * that can be overridden at the organization level.
 */
class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            // General settings
            [
                'key' => 'date_format',
                'value' => 'Y-m-d',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default date format for display',
                'is_public' => true,
            ],
            [
                'key' => 'time_format',
                'value' => 'H:i',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default time format for display',
                'is_public' => true,
            ],
            [
                'key' => 'datetime_format',
                'value' => 'Y-m-d H:i',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default datetime format for display',
                'is_public' => true,
            ],
            [
                'key' => 'timezone',
                'value' => 'UTC',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default timezone',
                'is_public' => true,
            ],

            // Financial settings
            [
                'key' => 'currency_default',
                'value' => 'EUR',
                'type' => 'string',
                'group' => 'financial',
                'description' => 'Default currency code (ISO 4217)',
                'is_public' => true,
            ],
            [
                'key' => 'currency_decimal_separator',
                'value' => '.',
                'type' => 'string',
                'group' => 'financial',
                'description' => 'Decimal separator for currency display',
                'is_public' => true,
            ],
            [
                'key' => 'currency_thousands_separator',
                'value' => ',',
                'type' => 'string',
                'group' => 'financial',
                'description' => 'Thousands separator for currency display',
                'is_public' => true,
            ],

            // API settings
            [
                'key' => 'pagination.default_size',
                'value' => 25,
                'type' => 'integer',
                'group' => 'api',
                'description' => 'Default pagination size for API responses',
                'is_public' => true,
            ],
            [
                'key' => 'pagination.max_size',
                'value' => 100,
                'type' => 'integer',
                'group' => 'api',
                'description' => 'Maximum allowed pagination size',
                'is_public' => true,
            ],

            // Notification settings
            [
                'key' => 'notifications.email_enabled',
                'value' => true,
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Enable email notifications',
                'is_public' => false,
            ],
            [
                'key' => 'notifications.database_enabled',
                'value' => true,
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Enable database notifications',
                'is_public' => false,
            ],

            // Security settings
            [
                'key' => 'security.session_lifetime',
                'value' => 120,
                'type' => 'integer',
                'group' => 'security',
                'description' => 'Session lifetime in minutes',
                'is_public' => false,
            ],
            [
                'key' => 'security.password_min_length',
                'value' => 8,
                'type' => 'integer',
                'group' => 'security',
                'description' => 'Minimum password length',
                'is_public' => true,
            ],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
