<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Jobs\BlaFastJob;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 17 fixture: records what a REAL queue worker sees inside the job.
 */
class RecordTeamJob extends BlaFastJob
{
    public function handle(): void
    {
        cache()->put('record-team-job', [
            'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
            'context_org_id' => organization_context()->id(),
            'has_context' => organization_context()->hasContext(),
        ]);
    }
}
