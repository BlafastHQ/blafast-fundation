<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Illuminate\Console\Command;

class BlafastCommand extends Command
{
    public $signature = 'blafast:info';

    public $description = 'Display Blafast Foundation module information';

    public function handle(): int
    {
        $this->info('Blafast Foundation Module');
        $this->line('Version: 1.0.0');
        $this->line('A comprehensive foundation for the BlaFast ERP system');

        return self::SUCCESS;
    }
}
