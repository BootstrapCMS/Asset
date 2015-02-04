<?php

namespace Lightgear\Asset\Console\Commands;

use Illuminate\Console\Command;

class CleanAssets extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'asset:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Delete existing published assets";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $asset = $this->laravel->make('asset');

        $asset->clean();

        $this->line('Cleaned up all published assets.');
    }
}
