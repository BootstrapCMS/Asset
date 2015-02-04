<?php

namespace Lightgear\Asset\Console\Commands;

use Illuminate\Console\Command;

class GenerateAssets extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'asset:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Generate and publish the registered assets";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $asset = $this->laravel->make('asset');

        $asset->clean();
        $asset->styles();
        $asset->scripts();

        $this->line('Generated and published assets.');
    }
}
