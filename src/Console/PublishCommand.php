<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Laravel\Sail\Sail;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:publish')]
class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the Laravel Sail Docker files';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('vendor:publish', ['--tag' => 'sail-docker']);
        $this->call('vendor:publish', ['--tag' => 'sail-database']);

        Sail::runPublishingHooks($this);
    }
}
