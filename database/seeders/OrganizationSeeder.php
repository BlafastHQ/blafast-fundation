<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Models\Address;
use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme-corporation',
                'vat_number' => 'US123456789',
                'contact_details' => [
                    'email' => 'contact@acme.com',
                    'phone' => '+1 (555) 123-4567',
                    'website' => 'https://www.acme.com',
                    'linkedin' => 'https://linkedin.com/company/acme',
                    'twitter' => 'acmecorp',
                ],
                'settings' => [
                    'date_format' => 'Y-m-d',
                    'timezone' => 'America/New_York',
                    'currency' => 'USD',
                    'language' => 'en',
                    'employees_count' => 5000,
                    'multi_currency' => true,
                    'advanced_reporting' => true,
                ],
                'is_active' => true,
                'peppol_id' => '9915:US123456789',
                'address' => [
                    'type' => Address::TYPE_HEADQUARTERS,
                    'label' => 'Headquarters',
                    'line_1' => '123 Business Avenue',
                    'line_2' => 'Suite 500',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country_iso' => 'US',
                    'is_verified' => true,
                ],
            ],
            [
                'name' => 'TechStart Ltd',
                'slug' => 'techstart-ltd',
                'vat_number' => 'GB987654321',
                'contact_details' => [
                    'email' => 'hello@techstart.co.uk',
                    'phone' => '+44 20 1234 5678',
                    'website' => 'https://www.techstart.co.uk',
                    'linkedin' => 'https://linkedin.com/company/techstart',
                ],
                'settings' => [
                    'date_format' => 'd/m/Y',
                    'timezone' => 'Europe/London',
                    'currency' => 'GBP',
                    'language' => 'en',
                    'employees_count' => 25,
                ],
                'is_active' => true,
                'peppol_id' => null,
                'address' => [
                    'type' => Address::TYPE_HEADQUARTERS,
                    'label' => 'Head Office',
                    'line_1' => '42 Tech Street',
                    'line_2' => null,
                    'city' => 'London',
                    'state' => null,
                    'postal_code' => 'EC1A 1BB',
                    'country_iso' => 'GB',
                    'is_verified' => true,
                ],
            ],
            [
                'name' => 'Innovate Solutions GmbH',
                'slug' => 'innovate-solutions-gmbh',
                'vat_number' => 'DE123987456',
                'contact_details' => [
                    'email' => 'info@innovate.de',
                    'phone' => '+49 30 12345678',
                    'website' => 'https://www.innovate.de',
                    'linkedin' => 'https://linkedin.com/company/innovate-solutions',
                ],
                'settings' => [
                    'date_format' => 'd.m.Y',
                    'timezone' => 'Europe/Berlin',
                    'currency' => 'EUR',
                    'language' => 'de',
                    'employees_count' => 150,
                    'multi_currency' => true,
                ],
                'is_active' => true,
                'peppol_id' => '9930:DE123987456',
                'address' => [
                    'type' => Address::TYPE_HEADQUARTERS,
                    'label' => 'Hauptsitz',
                    'line_1' => 'Innovationsstraße 10',
                    'line_2' => null,
                    'city' => 'Berlin',
                    'state' => 'Berlin',
                    'postal_code' => '10115',
                    'country_iso' => 'DE',
                    'is_verified' => true,
                ],
            ],
            [
                'name' => 'Digital Creators SARL',
                'slug' => 'digital-creators-sarl',
                'vat_number' => 'FR456789123',
                'contact_details' => [
                    'email' => 'contact@digitalcreators.fr',
                    'phone' => '+33 1 23 45 67 89',
                    'website' => 'https://www.digitalcreators.fr',
                ],
                'settings' => [
                    'date_format' => 'd/m/Y',
                    'timezone' => 'Europe/Paris',
                    'currency' => 'EUR',
                    'language' => 'fr',
                    'employees_count' => 12,
                ],
                'is_active' => true,
                'peppol_id' => null,
                'address' => [
                    'type' => Address::TYPE_HEADQUARTERS,
                    'label' => 'Siège Social',
                    'line_1' => '15 Rue de la République',
                    'line_2' => null,
                    'city' => 'Paris',
                    'state' => 'Île-de-France',
                    'postal_code' => '75001',
                    'country_iso' => 'FR',
                    'is_verified' => true,
                ],
            ],
            [
                'name' => 'Global Trading Inc',
                'slug' => 'global-trading-inc',
                'vat_number' => 'CA789456123',
                'contact_details' => [
                    'email' => 'info@globaltrading.ca',
                    'phone' => '+1 (416) 555-9876',
                    'website' => 'https://www.globaltrading.ca',
                    'linkedin' => 'https://linkedin.com/company/global-trading',
                ],
                'settings' => [
                    'date_format' => 'Y-m-d',
                    'timezone' => 'America/Toronto',
                    'currency' => 'CAD',
                    'language' => 'en',
                    'employees_count' => 75,
                    'multi_currency' => true,
                ],
                'is_active' => true,
                'peppol_id' => null,
                'address' => [
                    'type' => Address::TYPE_HEADQUARTERS,
                    'label' => 'Corporate Office',
                    'line_1' => '789 Commerce Boulevard',
                    'line_2' => 'Floor 12',
                    'city' => 'Toronto',
                    'state' => 'ON',
                    'postal_code' => 'M5H 2N2',
                    'country_iso' => 'CA',
                    'is_verified' => true,
                ],
            ],
            [
                'name' => 'Startup Hub Pty Ltd',
                'slug' => 'startup-hub-pty-ltd',
                'vat_number' => 'AU321654987',
                'contact_details' => [
                    'email' => 'hello@startuphub.com.au',
                    'phone' => '+61 2 9876 5432',
                    'website' => 'https://www.startuphub.com.au',
                ],
                'settings' => [
                    'date_format' => 'd/m/Y',
                    'timezone' => 'Australia/Sydney',
                    'currency' => 'AUD',
                    'language' => 'en',
                    'employees_count' => 8,
                ],
                'is_active' => true,
                'peppol_id' => null,
                'address' => [
                    'type' => Address::TYPE_HEADQUARTERS,
                    'label' => 'Main Office',
                    'line_1' => '56 Innovation Road',
                    'line_2' => null,
                    'city' => 'Sydney',
                    'state' => 'NSW',
                    'postal_code' => '2000',
                    'country_iso' => 'AU',
                    'is_verified' => true,
                ],
            ],
            [
                'name' => 'Inactive Corp',
                'slug' => 'inactive-corp',
                'vat_number' => null,
                'contact_details' => [
                    'email' => 'archive@inactive.com',
                ],
                'settings' => [
                    'date_format' => 'Y-m-d',
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en',
                ],
                'is_active' => false,
                'peppol_id' => null,
                'address' => null,
            ],
        ];

        foreach ($organizations as $orgData) {
            $addressData = $orgData['address'] ?? null;
            unset($orgData['address']);

            $organization = Organization::create($orgData);

            if ($addressData) {
                $country = Country::where('iso_alpha_2', $addressData['country_iso'])->first();
                unset($addressData['country_iso']);

                if ($country) {
                    $address = $organization->addAddress(array_merge($addressData, [
                        'country_id' => $country->id,
                    ]), true);

                    // Set the primary address on the organization
                    $organization->update(['address_id' => $address->id]);
                }
            }
        }
    }
}
