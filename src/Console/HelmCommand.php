<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:helm')]
class HelmCommand extends Command
{
    use Concerns\InteractsWithDocker;
    use Concerns\InteractsWithHelm;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:helm
                            {--chart-version= : Version to use for the chart}
                            {--bump= : Bump the version (patch, minor, major, no)}
                            {--no-version-update : Skip version update in Chart.yaml}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate Helm chart without building Docker images';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->output->writeln('');
        $this->components->info('📦 Regenerating Helm Chart...');
        $this->output->writeln('');

        // Handle version if provided
        $versionOption = $this->option('chart-version');
        $bumpOption = $this->option('bump');
        $updateVersion = ! $this->option('no-version-update');

        $currentVersion = config('sail.build.version', '1.0.0');
        $version = $versionOption ?? $currentVersion;

        if ($versionOption) {
            \Illuminate\Support\Facades\Config::set('sail.build.version', $version);
        }

        if ($bumpOption && $version !== null && $bumpOption !== 'no') {
            $version = $this->bumpVersion($version, $bumpOption);
            \Illuminate\Support\Facades\Config::set('sail.build.version', $version);
            $this->output->writeln('  <fg=green>✓</> Version bumped to: '.$version);
            $this->output->writeln('');
        }

        // Build Helm chart (always validates by default)
        $this->buildHelm($updateVersion);

        $this->output->writeln('');
        $this->components->info('✅ Helm Chart regeneration complete!');
        $this->output->writeln('');
        $this->output->writeln('  <fg=blue>Chart location:</> '.base_path('helm'));
        $this->output->writeln('');

        return 0;
    }
}
