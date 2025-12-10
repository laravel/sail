<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Attribute\AsCommand;

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
                            {--bump= : Bump the version (patch, minor, major, no)}';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $bump = $this->option('bump');

        if ($bump && ! in_array($bump, ['patch', 'minor', 'major', 'no'], true)) {
            $this->components->error('Invalid bump option. Use patch, minor, major, or no.');

            return 1;
        }

        $config = $this->configFromOptions($bump) ?? $this->getConfig($this->option('use-previous'));

        if ($this->validationFailed) {
            return 1;
        }

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
            $this->writeConfig($environments, $architectures, $repository);
        }

        foreach ($environments as $environment) {
            if (! in_array($environment, $this->environments)) {
                $this->components->error('Invalid environment ['.implode(',', $environment).'].');

                return 1;
            }
            $this->buildDockerImages($environment, $architectures, $repository);
        }

        $this->buildHelm();
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

        $overridesProvided = $envOption !== null
            || $archOption !== null
            || $repoOption !== null
            || $orgOption !== null
            || $domainsOption !== null
            || $versionOption !== null
            || $pushOption === true;

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
        ];
    }

    /**
     * Validate CLI overrides against allowed values.
     */
    protected function hasInvalidOptions(array $environments, array $architectures, string $repository): bool
    {
        $invalidEnvs = array_diff($environments, $this->environments);
        if ($invalidEnvs) {
            $this->components->error('Invalid environments: '.implode(', ', $invalidEnvs).'. Allowed: '.implode(', ', $this->environments));

            return true;
        }

        $invalidArchs = array_diff($architectures, $this->archs);
        if ($invalidArchs) {
            $this->components->error('Invalid architectures: '.implode(', ', $invalidArchs).'. Allowed: '.implode(', ', $this->archs));

            return true;
        }

        $allowedRepositories = array_keys($this->repositories);
        $allowedRepositories[] = 'none';

        if ($repository && ! in_array($repository, $allowedRepositories, true)) {
            $this->components->error('Invalid repository: '.$repository.'. Allowed: '.implode(', ', $allowedRepositories));

            return true;
        }

        return false;
    }
}
