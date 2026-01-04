<?php

declare(strict_types=1);

// Controllers architecture
arch('controllers')
    ->expect('Blafast\Foundation\Http\Controllers')
    ->toExtend('Illuminate\Routing\Controller')
    ->toHaveSuffix('Controller');

// Models architecture
arch('models')
    ->expect('Blafast\Foundation\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toUse('Illuminate\Database\Eloquent\Concerns\HasUuids');

// Note: Service classes naming convention is flexible
// Some services like BlaFastPermissionRegistrar, ModuleRegistry, and ExecPermissionChecker
// don't follow the "Service" suffix convention by design

// No debug statements
arch('no debug statements')
    ->expect('Blafast\Foundation')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'print_r']);

// Strict types
arch('strict types')
    ->expect('Blafast\Foundation')
    ->toUseStrictTypes();

// Commands architecture
arch('commands')
    ->expect('Blafast\Foundation\Commands')
    ->toExtend('Illuminate\Console\Command')
    ->toHaveSuffix('Command');

// Jobs architecture
arch('jobs')
    ->expect('Blafast\Foundation\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
    ->ignoring('Blafast\Foundation\Jobs\Middleware');

// Middleware architecture
arch('middleware')
    ->expect('Blafast\Foundation\Http\Middleware')
    ->toHaveMethod('handle');

// Policies architecture
arch('policies')
    ->expect('Blafast\Foundation\Policies')
    ->toHaveSuffix('Policy');

// Resources architecture
arch('resources')
    ->expect('Blafast\Foundation\Http\Resources')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource')
    ->toHaveSuffix('Resource');
