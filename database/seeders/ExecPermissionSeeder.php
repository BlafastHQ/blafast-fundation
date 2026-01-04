<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Services\BlaFastPermissionRegistrar;
use Illuminate\Database\Seeder;

/**
 * Seeder for exec permissions.
 *
 * Syncs all CRUD and exec permissions for registered models,
 * and applies default rights to roles.
 */
class ExecPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(BlaFastPermissionRegistrar $registrar): void
    {
        $this->command->info('Syncing exec permissions...');

        $registrar->syncAll();

        $this->command->info('Exec permissions synced successfully.');
    }
}
