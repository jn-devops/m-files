<?php

namespace Homeful\MFiles\Commands;

use Illuminate\Console\Command;

class MFilesCommand extends Command
{
    public $signature = 'm-files';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
