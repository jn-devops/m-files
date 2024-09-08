<?php

namespace Homeful\MFiles;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Homeful\MFiles\Commands\MFilesCommand;

class MFilesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('m-files')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_m-files_table')
            ->hasCommand(MFilesCommand::class);
    }
}
