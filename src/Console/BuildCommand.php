<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'sail:build')]
class BuildCommand extends Command
{
    use Concerns\InteractsWithDocker;
    use Concerns\InteractsWithHelm;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:build
                            {--environments= : Comma-separated environments (local,production)}
                            {--architectures= : Comma-separated architectures (linux/amd64,linux/arm64,...)}
                            {--repository= : Container registry host (or "none" for local only)}
                            {--organization= : Registry organization / namespace}
                            {--domains= : Comma-separated deployment domains}
                            {--build-version= : Version to use for the build}
                            {--push : Push built images to the registry}
                            {--use-previous : Reuse the last saved build configuration without prompts}
                            {--bump= : Bump the version (patch, minor, major, no)}
                            {--dry-run : Preview what would be built without executing}
                            {--remove-vendor-node-modules : Remove vendor/ and node_modules/ from final image (default: true)}
                            {--keep-vendor-node-modules : Keep vendor/ and node_modules/ in final image}';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->components->info('🔍 DRY RUN MODE: No changes will be made');
            $this->output->writeln('');
        }

        // Validate prerequisites
        if (! $this->validatePrerequisites($dryRun)) {
            return 1;
        }

        $bump = $this->option('bump');

        if ($bump && ! in_array($bump, ['patch', 'minor', 'major', 'no'], true)) {
            $this->components->error('Invalid bump option. Use patch, minor, major, or no.');
            $this->components->warn('💡 Tip: Valid options are: patch, minor, major, no');

            return 1;
        }

        $config = $this->configFromOptions($bump);

        if ($this->validationFailed) {
            return 1;
        }

        $config = $config ?? $this->getConfig($this->option('use-previous'));

        if (! $config) {
            $environments = $this->gatherEnvironmentsInteractively();
            $architectures = $this->gatherArchitecturesInteractively();
            $repository = $this->gatherRepositoryInteractively($environments);
            $this->gatherDeploymentDomainsInteractively();
            $version = $this->getVersionChoice();
            if ($bump && $version !== null && $bump !== 'no') {
                $version = $this->bumpVersion($version, $bump);
            }
            $this->writeConfig($environments, $architectures, $repository);
        } else {
            $environments = $config['environments'] ?? [];
            $architectures = $config['architectures'] ?? [];
            $repository = $config['repository'] ?? '';
            if ($bump && $config['version'] !== null) {
                $config['version'] = $this->bumpVersion($config['version'], $bump);
            }
            // Ensure removeVendorNodeModules is set from config if not already set
            if (! isset($this->removeVendorNodeModules) && isset($config['remove_vendor_node_modules'])) {
                $this->removeVendorNodeModules = (bool) $config['remove_vendor_node_modules'];
            }
            $this->writeConfig($environments, $architectures, $repository);
        }

        // Update Helm chart version BEFORE building (so we can revert if build fails)
        $originalVersion = null;
        $versionUpdated = false;
        $chartExisted = false;
        if (! $dryRun) {
            $version = config('sail.build.version');
            if ($version) {
                $result = $this->updateHelmChartVersion($version);
                $originalVersion = $result['originalVersion'] ?? null;
                $chartExisted = $result['chartExisted'] ?? false;
                $versionUpdated = true;
            }
        }

        $buildFailed = false;
        foreach ($environments as $environment) {
            if (! in_array($environment, $this->environments)) {
                $this->components->error('Invalid environment ['.implode(',', $environment).'].');
                $this->components->warn('💡 Tip: Valid environments are: '.implode(', ', $this->environments));

                return 1;
            }

            if ($dryRun) {
                $this->components->info("Would build Docker images for environment: {$environment}");
                $this->output->writeln('  Architectures: '.implode(', ', $architectures));
                $this->output->writeln('  Repository: '.($repository === 'none' ? 'local only' : $repository));
            } else {
                $result = $this->buildDockerImages($environment, $architectures, $repository);
                if ($result !== 0) {
                    $buildFailed = true;
                    break;
                }
            }
        }

        // Revert version if build failed
        if ($buildFailed && $versionUpdated && $chartExisted && $originalVersion !== null) {
            $this->output->writeln('');
            $this->components->error('Build failed! Reverting Helm chart version...');
            $this->revertHelmChartVersion($originalVersion);
            $this->output->writeln('  <fg=yellow>✓</> Version reverted to: '.$originalVersion);
            $this->output->writeln('');

            return 1;
        }

        if ($dryRun) {
            $this->components->info('Would build Helm chart');
            $this->output->writeln('');
            $this->components->info('✅ Dry run completed. Use without --dry-run to execute.');
        } else {
            // If chart existed, version already updated; if not, create with version
            $this->buildHelm(! $chartExisted);
        }
    }

    /**
     * Build configuration from CLI options if provided.
     */
    protected function configFromOptions(?string $bumpOption = null): ?array
    {
        $envOption = $this->option('environments');
        $archOption = $this->option('architectures');
        $repoOption = $this->option('repository');
        $orgOption = $this->option('organization');
        $domainsOption = $this->option('domains');
        $versionOption = $this->option('build-version');
        $pushOption = $this->option('push');
        $removeVendorNodeModulesOption = $this->option('remove-vendor-node-modules');
        $keepVendorNodeModulesOption = $this->option('keep-vendor-node-modules');

        $overridesProvided = $envOption !== null
            || $archOption !== null
            || $repoOption !== null
            || $orgOption !== null
            || $domainsOption !== null
            || $versionOption !== null
            || $pushOption === true
            || $removeVendorNodeModulesOption === true
            || $keepVendorNodeModulesOption === true;

        if (! $overridesProvided) {
            return null;
        }

        $config = config('sail.build');

        $environments = $envOption
            ? array_filter(array_map('trim', explode(',', $envOption)))
            : explode(',', $config['environments']);

        $architectures = $archOption
            ? array_filter(array_map('trim', explode(',', $archOption)))
            : explode(',', $config['architectures']);

        $repository = $repoOption ?? $config['repository'];
        $this->useRepository = $repository !== 'none';

        $this->push = $pushOption === true ? true : (bool) $config['push'];

        $this->organization = $orgOption ?? $config['organization'];

        $this->deploymentDomains = $domainsOption
            ? array_filter(array_map('trim', explode(',', $domainsOption)))
            : explode(',', config('sail.deploy.domains'));

        $version = $versionOption ?? $config['version'];
        if ($version !== null) {
            Config::set('sail.build.version', $version);
        }

        if ($bumpOption && $version !== null && $bumpOption !== 'no') {
            $version = $this->bumpVersion($version, $bumpOption);
            Config::set('sail.build.version', $version);
        }

        // Handle remove/keep vendor and node_modules options
        if ($keepVendorNodeModulesOption === true) {
            $this->removeVendorNodeModules = false;
        } elseif ($removeVendorNodeModulesOption === true) {
            $this->removeVendorNodeModules = true;
        } else {
            $this->removeVendorNodeModules = $config['remove_vendor_node_modules'] ?? true;
        }

        if ($this->hasInvalidOptions($environments, $architectures, $repository)) {
            $this->validationFailed = true;

            return null;
        }

        return [
            'environments' => $environments,
            'architectures' => $architectures,
            'repository' => $repository,
            'organization' => $this->organization,
            'push' => $this->push,
            'version' => $version,
            'remove_vendor_node_modules' => $this->removeVendorNodeModules,
        ];
    }

    /**
     * Validate CLI overrides against allowed values.
     */
    protected function hasInvalidOptions(array $environments, array $architectures, string $repository): bool
    {
        $hasErrors = false;

        $invalidEnvs = array_diff($environments, $this->environments);
        if ($invalidEnvs) {
            $this->components->error('Invalid environments: '.implode(', ', $invalidEnvs));
            $this->components->warn('💡 Tip: Valid environments are: '.implode(', ', $this->environments));
            $this->output->writeln('');
            $hasErrors = true;
        }

        $invalidArchs = array_diff($architectures, $this->archs);
        if ($invalidArchs) {
            $this->components->error('Invalid architectures: '.implode(', ', $invalidArchs));
            $this->components->warn('💡 Tip: Valid architectures are: '.implode(', ', array_slice($this->archs, 0, 5)).'...');
            $this->output->writeln('   See all available architectures in the documentation.');
            $this->output->writeln('');
            $hasErrors = true;
        }

        $allowedRepositories = array_keys($this->repositories);
        $allowedRepositories[] = 'none';

        // Check if repository is valid
        if ($repository && ! in_array($repository, $allowedRepositories, true)) {
            // Allow full ECR URLs (e.g., 888657980245.dkr.ecr.us-east-1.amazonaws.com)
            $isECR = $this->isECRRegistry($repository);
            // Allow full ACR URLs (e.g., myregistry.azurecr.io)
            $isACR = $this->isACRRegistry($repository);
            // Allow any URL that looks like a registry (contains dots, no spaces)
            $isFullRegistryUrl = strpos($repository, '.') !== false &&
                                 strpos($repository, ' ') === false &&
                                 preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/', $repository);

            if (! $isECR && ! $isACR && ! $isFullRegistryUrl) {
                $this->components->error('Invalid repository: '.$repository);
                $this->components->warn('💡 Tip: Valid repositories are: '.implode(', ', array_slice($allowedRepositories, 0, 5)).'...');
                $this->output->writeln('   You can also use full registry URLs (e.g., registry.example.com, *.dkr.ecr.*.amazonaws.com, *.azurecr.io)');
                $this->output->writeln('   Use "none" for local-only builds.');
                $this->output->writeln('');
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    /**
     * Validate prerequisites (docker, docker buildx, helm).
     */
    protected function validatePrerequisites(bool $dryRun = false): bool
    {
        $this->components->info('Checking prerequisites...');

        $missing = [];
        $suggestions = [];

        // Check Docker
        $process = new Process(['docker', '--version']);
        $process->run();
        if (! $process->isSuccessful()) {
            $missing[] = 'Docker';
            $suggestions['Docker'] = 'Install Docker from https://docs.docker.com/get-docker/';
        } else {
            $this->output->writeln('  <fg=green>✓</> Docker: '.trim($process->getOutput()));
        }

        // Check Docker Buildx
        $process = new Process(['docker', 'buildx', 'version']);
        $process->run();
        if (! $process->isSuccessful()) {
            $missing[] = 'Docker Buildx';
            $suggestions['Docker Buildx'] = 'Enable buildx: docker buildx install';
        } else {
            $this->output->writeln('  <fg=green>✓</> Docker Buildx: '.trim($process->getOutput()));
        }

        // Check Helm
        $process = new Process(['helm', 'version', '--short']);
        $process->run();
        if (! $process->isSuccessful()) {
            $missing[] = 'Helm';
            $suggestions['Helm'] = 'Install Helm from https://helm.sh/docs/intro/install/';
        } else {
            $this->output->writeln('  <fg=green>✓</> Helm: '.trim($process->getOutput()));
        }

        if (! empty($missing)) {
            $this->output->writeln('');
            $this->components->error('Missing prerequisites: '.implode(', ', $missing));
            $this->output->writeln('');
            $this->components->warn('Installation suggestions:');
            foreach ($suggestions as $tool => $suggestion) {
                $this->output->writeln("  <fg=yellow>→</> {$tool}: {$suggestion}");
            }

            return false;
        }

        $this->output->writeln('');

        return true;
    }

    /**
     * Update Helm chart version before building.
     *
     * @return array{originalVersion: string|null, chartExisted: bool}
     */
    protected function updateHelmChartVersion(string $newVersion): array
    {
        $helmPath = base_path('helm');
        $chartPath = $helmPath.'/Chart.yaml';

        if (! file_exists($chartPath)) {
            // Chart doesn't exist yet, will be created in buildHelm
            $this->output->writeln('');
            $this->components->info('📦 Helm chart will be created with version: '.$newVersion);
            $this->output->writeln('');

            return ['originalVersion' => null, 'chartExisted' => false];
        }

        $this->output->writeln('');
        $this->components->info('📦 Updating Helm chart version...');

        $chart = \Symfony\Component\Yaml\Yaml::parseFile($chartPath);
        $originalVersion = $chart['version'] ?? null;

        $chart['version'] = $newVersion;
        $chart['appVersion'] = $newVersion;

        $yaml = \Symfony\Component\Yaml\Yaml::dump($chart, \Symfony\Component\Yaml\Yaml::DUMP_OBJECT_AS_MAP);
        file_put_contents($chartPath, $yaml);

        $this->output->writeln('  <fg=green>✓</> Chart version updated to: '.$newVersion);
        if ($originalVersion) {
            $this->output->writeln('  <fg=blue>→</> Previous version: '.$originalVersion);
        }
        $this->output->writeln('');

        return ['originalVersion' => $originalVersion, 'chartExisted' => true];
    }

    /**
     * Revert Helm chart version on build failure.
     */
    protected function revertHelmChartVersion(?string $originalVersion): void
    {
        if ($originalVersion === null) {
            return;
        }

        $helmPath = base_path('helm');
        $chartPath = $helmPath.'/Chart.yaml';

        if (! file_exists($chartPath)) {
            return;
        }

        $chart = \Symfony\Component\Yaml\Yaml::parseFile($chartPath);
        $chart['version'] = $originalVersion;
        $chart['appVersion'] = $originalVersion;

        $yaml = \Symfony\Component\Yaml\Yaml::dump($chart, \Symfony\Component\Yaml\Yaml::DUMP_OBJECT_AS_MAP);
        file_put_contents($chartPath, $yaml);
    }
}
