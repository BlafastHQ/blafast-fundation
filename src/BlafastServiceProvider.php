<?php

namespace Blafast\Blafast;

use Blafast\Blafast\Commands\BlafastCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BlafastServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('blafast-fundation')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_blafast_fundation_table')
            ->hasCommand(BlafastCommand::class);
    }
}
