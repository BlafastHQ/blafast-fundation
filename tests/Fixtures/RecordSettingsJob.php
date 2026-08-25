<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Jobs\BlaFastJob;
use Blafast\Foundation\Services\MetadataCacheService;

/**
 * Task 19 fixture: records what settings resolution and cache-key scoping look
 * like INSIDE a worker-processed job.
 */
class RecordSettingsJob extends BlaFastJob
{
    public function __construct(public string $marker)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $cache = app(MetadataCacheService::class);
        $buildKey = new \ReflectionMethod($cache, 'buildKey');

        cache()->put("record-settings-{$this->marker}", [
            'setting' => blafast_setting('probe.key', 'DEFAULT'),
            'cache_key' => $buildKey->invoke($cache, 'probe'),
        ]);
    }
}
