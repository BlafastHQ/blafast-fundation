<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = $this->getCountries();

        foreach ($countries as $countryData) {
            $currency = Currency::where('code', $countryData['currency_code'])->first();

            if (! $currency) {
                continue; // Skip if currency doesn't exist
            }

            Country::firstOrCreate(
                ['iso_alpha_2' => $countryData['iso_alpha_2']],
                [
                    'name' => $countryData['name'],
                    'iso_alpha_3' => $countryData['iso_alpha_3'],
                    'iso_numeric' => $countryData['iso_numeric'],
                    'phone_code' => $countryData['phone_code'],
                    'currency_id' => $currency->id,
                    'is_active' => $countryData['is_active'],
                ]
            );
        }
    }

    /**
     * Get the list of countries based on ISO 3166-1.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCountries(): array
    {
        return [
            // Europe
            ['name' => 'France', 'iso_alpha_2' => 'FR', 'iso_alpha_3' => 'FRA', 'iso_numeric' => '250', 'phone_code' => '+33', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Germany', 'iso_alpha_2' => 'DE', 'iso_alpha_3' => 'DEU', 'iso_numeric' => '276', 'phone_code' => '+49', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Italy', 'iso_alpha_2' => 'IT', 'iso_alpha_3' => 'ITA', 'iso_numeric' => '380', 'phone_code' => '+39', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Spain', 'iso_alpha_2' => 'ES', 'iso_alpha_3' => 'ESP', 'iso_numeric' => '724', 'phone_code' => '+34', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Netherlands', 'iso_alpha_2' => 'NL', 'iso_alpha_3' => 'NLD', 'iso_numeric' => '528', 'phone_code' => '+31', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Belgium', 'iso_alpha_2' => 'BE', 'iso_alpha_3' => 'BEL', 'iso_numeric' => '056', 'phone_code' => '+32', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Austria', 'iso_alpha_2' => 'AT', 'iso_alpha_3' => 'AUT', 'iso_numeric' => '040', 'phone_code' => '+43', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Portugal', 'iso_alpha_2' => 'PT', 'iso_alpha_3' => 'PRT', 'iso_numeric' => '620', 'phone_code' => '+351', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Greece', 'iso_alpha_2' => 'GR', 'iso_alpha_3' => 'GRC', 'iso_numeric' => '300', 'phone_code' => '+30', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'Ireland', 'iso_alpha_2' => 'IE', 'iso_alpha_3' => 'IRL', 'iso_numeric' => '372', 'phone_code' => '+353', 'currency_code' => 'EUR', 'is_active' => true],
            ['name' => 'United Kingdom', 'iso_alpha_2' => 'GB', 'iso_alpha_3' => 'GBR', 'iso_numeric' => '826', 'phone_code' => '+44', 'currency_code' => 'GBP', 'is_active' => true],
            ['name' => 'Switzerland', 'iso_alpha_2' => 'CH', 'iso_alpha_3' => 'CHE', 'iso_numeric' => '756', 'phone_code' => '+41', 'currency_code' => 'CHF', 'is_active' => true],
            ['name' => 'Sweden', 'iso_alpha_2' => 'SE', 'iso_alpha_3' => 'SWE', 'iso_numeric' => '752', 'phone_code' => '+46', 'currency_code' => 'SEK', 'is_active' => true],
            ['name' => 'Norway', 'iso_alpha_2' => 'NO', 'iso_alpha_3' => 'NOR', 'iso_numeric' => '578', 'phone_code' => '+47', 'currency_code' => 'NOK', 'is_active' => true],
            ['name' => 'Denmark', 'iso_alpha_2' => 'DK', 'iso_alpha_3' => 'DNK', 'iso_numeric' => '208', 'phone_code' => '+45', 'currency_code' => 'DKK', 'is_active' => true],
            ['name' => 'Poland', 'iso_alpha_2' => 'PL', 'iso_alpha_3' => 'POL', 'iso_numeric' => '616', 'phone_code' => '+48', 'currency_code' => 'PLN', 'is_active' => true],
            ['name' => 'Czech Republic', 'iso_alpha_2' => 'CZ', 'iso_alpha_3' => 'CZE', 'iso_numeric' => '203', 'phone_code' => '+420', 'currency_code' => 'CZK', 'is_active' => true],
            ['name' => 'Hungary', 'iso_alpha_2' => 'HU', 'iso_alpha_3' => 'HUN', 'iso_numeric' => '348', 'phone_code' => '+36', 'currency_code' => 'HUF', 'is_active' => true],
            ['name' => 'Romania', 'iso_alpha_2' => 'RO', 'iso_alpha_3' => 'ROU', 'iso_numeric' => '642', 'phone_code' => '+40', 'currency_code' => 'RON', 'is_active' => true],

            // North America
            ['name' => 'United States', 'iso_alpha_2' => 'US', 'iso_alpha_3' => 'USA', 'iso_numeric' => '840', 'phone_code' => '+1', 'currency_code' => 'USD', 'is_active' => true],
            ['name' => 'Canada', 'iso_alpha_2' => 'CA', 'iso_alpha_3' => 'CAN', 'iso_numeric' => '124', 'phone_code' => '+1', 'currency_code' => 'CAD', 'is_active' => true],
            ['name' => 'Mexico', 'iso_alpha_2' => 'MX', 'iso_alpha_3' => 'MEX', 'iso_numeric' => '484', 'phone_code' => '+52', 'currency_code' => 'MXN', 'is_active' => true],

            // Asia
            ['name' => 'Japan', 'iso_alpha_2' => 'JP', 'iso_alpha_3' => 'JPN', 'iso_numeric' => '392', 'phone_code' => '+81', 'currency_code' => 'JPY', 'is_active' => true],
            ['name' => 'China', 'iso_alpha_2' => 'CN', 'iso_alpha_3' => 'CHN', 'iso_numeric' => '156', 'phone_code' => '+86', 'currency_code' => 'CNY', 'is_active' => true],
            ['name' => 'South Korea', 'iso_alpha_2' => 'KR', 'iso_alpha_3' => 'KOR', 'iso_numeric' => '410', 'phone_code' => '+82', 'currency_code' => 'KRW', 'is_active' => true],
            ['name' => 'India', 'iso_alpha_2' => 'IN', 'iso_alpha_3' => 'IND', 'iso_numeric' => '356', 'phone_code' => '+91', 'currency_code' => 'INR', 'is_active' => true],
            ['name' => 'Singapore', 'iso_alpha_2' => 'SG', 'iso_alpha_3' => 'SGP', 'iso_numeric' => '702', 'phone_code' => '+65', 'currency_code' => 'SGD', 'is_active' => true],
            ['name' => 'Hong Kong', 'iso_alpha_2' => 'HK', 'iso_alpha_3' => 'HKG', 'iso_numeric' => '344', 'phone_code' => '+852', 'currency_code' => 'HKD', 'is_active' => true],
            ['name' => 'Thailand', 'iso_alpha_2' => 'TH', 'iso_alpha_3' => 'THA', 'iso_numeric' => '764', 'phone_code' => '+66', 'currency_code' => 'THB', 'is_active' => true],
            ['name' => 'Malaysia', 'iso_alpha_2' => 'MY', 'iso_alpha_3' => 'MYS', 'iso_numeric' => '458', 'phone_code' => '+60', 'currency_code' => 'MYR', 'is_active' => true],
            ['name' => 'Indonesia', 'iso_alpha_2' => 'ID', 'iso_alpha_3' => 'IDN', 'iso_numeric' => '360', 'phone_code' => '+62', 'currency_code' => 'IDR', 'is_active' => true],
            ['name' => 'Philippines', 'iso_alpha_2' => 'PH', 'iso_alpha_3' => 'PHL', 'iso_numeric' => '608', 'phone_code' => '+63', 'currency_code' => 'PHP', 'is_active' => true],
            ['name' => 'Israel', 'iso_alpha_2' => 'IL', 'iso_alpha_3' => 'ISR', 'iso_numeric' => '376', 'phone_code' => '+972', 'currency_code' => 'ILS', 'is_active' => true],
            ['name' => 'Turkey', 'iso_alpha_2' => 'TR', 'iso_alpha_3' => 'TUR', 'iso_numeric' => '792', 'phone_code' => '+90', 'currency_code' => 'TRY', 'is_active' => true],

            // Oceania
            ['name' => 'Australia', 'iso_alpha_2' => 'AU', 'iso_alpha_3' => 'AUS', 'iso_numeric' => '036', 'phone_code' => '+61', 'currency_code' => 'AUD', 'is_active' => true],
            ['name' => 'New Zealand', 'iso_alpha_2' => 'NZ', 'iso_alpha_3' => 'NZL', 'iso_numeric' => '554', 'phone_code' => '+64', 'currency_code' => 'NZD', 'is_active' => true],

            // South America
            ['name' => 'Brazil', 'iso_alpha_2' => 'BR', 'iso_alpha_3' => 'BRA', 'iso_numeric' => '076', 'phone_code' => '+55', 'currency_code' => 'BRL', 'is_active' => true],

            // Africa
            ['name' => 'South Africa', 'iso_alpha_2' => 'ZA', 'iso_alpha_3' => 'ZAF', 'iso_numeric' => '710', 'phone_code' => '+27', 'currency_code' => 'ZAR', 'is_active' => true],

            // Eastern Europe
            ['name' => 'Russia', 'iso_alpha_2' => 'RU', 'iso_alpha_3' => 'RUS', 'iso_numeric' => '643', 'phone_code' => '+7', 'currency_code' => 'RUB', 'is_active' => true],
        ];
    }
}
