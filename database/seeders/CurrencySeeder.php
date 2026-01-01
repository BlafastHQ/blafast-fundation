<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = $this->getCurrencies();

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }

    /**
     * Get the list of currencies based on ISO 4217.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCurrencies(): array
    {
        return [
            // Major World Currencies
            [
                'name' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'British Pound',
                'code' => 'GBP',
                'symbol' => '£',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Japanese Yen',
                'code' => 'JPY',
                'symbol' => '¥',
                'decimal_places' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Swiss Franc',
                'code' => 'CHF',
                'symbol' => 'CHF',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Canadian Dollar',
                'code' => 'CAD',
                'symbol' => 'C$',
                'decimal_places' => 2,
                'is_active' => true,
            ],

            // Other Major Currencies
            [
                'name' => 'Australian Dollar',
                'code' => 'AUD',
                'symbol' => 'A$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Chinese Yuan Renminbi',
                'code' => 'CNY',
                'symbol' => '¥',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Hong Kong Dollar',
                'code' => 'HKD',
                'symbol' => 'HK$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'New Zealand Dollar',
                'code' => 'NZD',
                'symbol' => 'NZ$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Swedish Krona',
                'code' => 'SEK',
                'symbol' => 'kr',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'South Korean Won',
                'code' => 'KRW',
                'symbol' => '₩',
                'decimal_places' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Singapore Dollar',
                'code' => 'SGD',
                'symbol' => 'S$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Norwegian Krone',
                'code' => 'NOK',
                'symbol' => 'kr',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Mexican Peso',
                'code' => 'MXN',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Indian Rupee',
                'code' => 'INR',
                'symbol' => '₹',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Russian Ruble',
                'code' => 'RUB',
                'symbol' => '₽',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'South African Rand',
                'code' => 'ZAR',
                'symbol' => 'R',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Turkish Lira',
                'code' => 'TRY',
                'symbol' => '₺',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Brazilian Real',
                'code' => 'BRL',
                'symbol' => 'R$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Danish Krone',
                'code' => 'DKK',
                'symbol' => 'kr',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Polish Zloty',
                'code' => 'PLN',
                'symbol' => 'zł',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Thai Baht',
                'code' => 'THB',
                'symbol' => '฿',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Czech Koruna',
                'code' => 'CZK',
                'symbol' => 'Kč',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Indonesian Rupiah',
                'code' => 'IDR',
                'symbol' => 'Rp',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Hungarian Forint',
                'code' => 'HUF',
                'symbol' => 'Ft',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Romanian Leu',
                'code' => 'RON',
                'symbol' => 'lei',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Israeli New Shekel',
                'code' => 'ILS',
                'symbol' => '₪',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Philippine Peso',
                'code' => 'PHP',
                'symbol' => '₱',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Malaysian Ringgit',
                'code' => 'MYR',
                'symbol' => 'RM',
                'decimal_places' => 2,
                'is_active' => true,
            ],
        ];
    }
}
