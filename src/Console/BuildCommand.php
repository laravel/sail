<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use MirazMac\DotEnv\Writer;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'sail:build')]
class BuildCommand extends Command
{
    use Concerns\InteractsWithDocker;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:build';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {

        $config = $this->getConfig();

        if (! $config) {
            $environments = $this->gatherEnvironmentsInteractively();
            $architectures = $this->gatherArchitecturesInteractively();
            $repository = $this->gatherRepositoryInteractively($environments);
            $this->writeConfig($environments, $architectures, $repository);
        } else {
            $environments = $config['environments'] ?? [];
            $architectures = $config['architectures'] ?? [];
            $repository = $config['repository'] ?? '';
            $this->writeConfig($environments, $architectures, $repository);
        }

        foreach ($environments as $environment) {
            if (! in_array($environment, $this->environments)) {
                $this->components->error('Invalid environment [' . implode(',', $environment) . '].');
                return 1;
            }
            $this->buildDockerImages($environment, $architectures, $repository);
        }
    }
}
