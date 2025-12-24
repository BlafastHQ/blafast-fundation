<?php

namespace Blafast\Blafast\Commands;

use Illuminate\Console\Command;

class BlafastCommand extends Command
{
    public $signature = 'blafast-fundation';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
