<?php

namespace Laravel\Sail\Console\Concerns;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use MirazMac\DotEnv\Writer;
use Symfony\Component\Process\Process;

trait InteractsWithDocker
{
    /**
     * The available enviroments that may be interacted with.
     *
     * @var array<string>
     */
    protected $environments = [
        'local',
        'production',
    ];

    /**
     * The available architectures that may be interacted with.
     *
     * @var array<string>
     */
    protected $archs = [
        'linux/amd64',
        'linux/arm64',
        'linux/arm/v7',
        'linux/arm/v6',
        'linux/ppc64le',
        'linux/s390x',
    ];

    /**
     * The default platforms to be used.
     *
     * @var array<string>
     */
    protected $defaultArchs = [
        'linux/amd64',
        'linux/arm64',
    ];

    /**
     * The default enviroments to interacted with.
     *
     * @var array<string>
     */
    protected $defaultEnvs = [
        'production',
    ];

    /**
     * The available repositories that may be interacted with.
     *
     * @var array<string>
     */
    protected $repositories = [
        'ghcr.io' => 'GitHub Container Registry',
        'registry.gitlab.com' => 'GitLab Container Registry',
        'azurecr' => 'Azure Container Registry',
        'docker.io' => 'Docker Hub',
        'gcr' => 'Google Container Registry',
        'ecr' => 'Amazon Elastic Container Registry',
        'quay.io' => 'Quay.io',
        'harbor' => 'Harbor',
        'none' => 'None - Local Build',
    ];

    /**
     * Indicates if the repository should be used.
     */
    protected bool $useRepository = false;

    /**
     * Indicates if the images should be pushed to the repository.
     */
    protected bool $push = false;

    /**
     * Indicates if the configuration should be reused.
     */
    protected bool $reuseConfig = false;

    /**
     * Indicates if CLI validation failed.
     */
    protected bool $validationFailed = false;

    /**
     * The name of the organization to be used.
     */
    protected ?string $organization = null;

    /**
     * The domains to be used for deployment.
     *
     * @var array<string>|null
     */
    protected ?array $deploymentDomains = null;

    /**
     * Indicates if vendor/ and node_modules/ should be removed from the final image.
     */
    protected ?bool $removeVendorNodeModules = null;

    /**
     * Gather the desired Sail services using an interactive prompt.
     *
     * @return array
     */
    protected function gatherEnvironmentsInteractively()
    {
        $defaultEnvs = explode(',', config('sail.build.environments')) ?? $this->defaultEnvs;
        $environments = array_unique(array_merge($this->defaultEnvs, $this->environments));

        sort($environments);
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which environments would you like to use?',
                options: $environments,
                default: $defaultEnvs,
                scroll: count($environments) > 20 ? 15 : count($environments),
                required: true,
            );
        }

        return $this->choice('Which environments would you like to use?', $$environments, 0, null, true);
    }

    /**
     * Gather the desired Sail repositories using an interactive prompt.
     *
     * @return string
     */
    protected function gatherRepositoryInteractively(array $environments)
    {
        if (! in_array('production', $environments)) {
            return 'none';
        }
        if (function_exists('\Laravel\Prompts\select')) {
            $repositories = \Laravel\Prompts\select(
                label: 'Which repository would you like to use?',
                options: $this->repositories,
                default: 'ghcr.io',
                scroll: count($this->repositories) > 20 ? 15 : count($this->repositories),
                required: true,
            );
        } else {
            $repositories = $this->choice('Which repository would you like to use?', $this->repositories, 0, null, false);
        }

        if ($repositories === 'none') {
            $this->useRepository = false;
        } else {
            $this->useRepository = true;
            $this->choosePush();
            $this->gatherOrtanizationNameInteractively();
        }

        return $repositories;
    }

    /**
     * Get a confirmation from the user to push the images to the repository.
     *
     * @return array
     */
    protected function choosePush()
    {
        if (function_exists('\Laravel\Prompts\confirm')) {
            $this->push = \Laravel\Prompts\confirm(
                label: 'Would you like to push the images to the repository?',
                default: config('sail.build.push', false),
            );
        } else {
            $this->push = $this->confirm('Would you like to push the images to the repository?', config('sail.build.push', false));
        }
    }

    /**
     * Gather the desired Sail domains using an interactive prompt.
     *
     * @return array
     */
    protected function gatherDeploymentDomainsInteractively()
    {
        $this->deploymentDomains = explode(',', config('sail.deploy.domains'));
        $domains = $this->deploymentDomains;
        $domains = array_unique(array_merge(['** Add new domain **'], $this->deploymentDomains));

        if (function_exists('\Laravel\Prompts\multiselect')) {
            $selected = \Laravel\Prompts\multiselect(
                label: 'Which repository would you like to use?',
                options: $domains,
                default: $this->deploymentDomains,
                scroll: count($domains) > 20 ? 15 : count($domains),
                required: true,
            );
        } else {
            $selected = $this->choice('Which repository would you like to use?', $domains, 0, null, false);
        }

        if (in_array('** Add new domain **', $selected)) {
            $this->deploymentDomains = array_diff($selected, ['** Add new domain **']);

            do {
                $this->gatherNewDomainInteractively();
            } while ($this->moreDomains());
        } else {
            $this->deploymentDomains = $selected;
        }

        return $this->deploymentDomains;
    }

    protected function moreDomains()
    {
        if (function_exists('\Laravel\Prompts\confirm')) {
            return \Laravel\Prompts\confirm(
                label: 'Would you like to add another domain?',
                default: false,
            );
        } else {
            return $this->confirm('Would you like to add another domain?', false);
        }
    }

    /**
     * Gather the desired Sail domains using an interactive prompt.
     *
     * @return string
     */
    protected function gatherNewDomainInteractively(): void
    {
        if (function_exists('\Laravel\Prompts\text')) {
            $this->deploymentDomains[] = \Laravel\Prompts\text(
                label: 'What is the new domain?',
                required: true,
            );
        } else {
            $this->deploymentDomains[] = $this->ask('What is the new domain?');
        }
    }

    /**
     * Gather the desired Sail architectures using an interactive prompt.
     *
     * @return array
     */
    protected function gatherArchitecturesInteractively()
    {
        $defaultArchs = explode(',', config('sail.build.architectures')) ?? $this->defaultArchs;
        $archs = array_unique(array_merge($this->defaultArchs, $this->archs));

        sort($archs);
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which architectures would you like to use?',
                options: $archs,
                default: $defaultArchs,
                scroll: count($archs) > 20 ? 15 : count($archs),
                required: true,
            );
        }

        return $this->choice('Which architectures would you like to use?', $archs, 0, null, true);
    }

    /**
     * Gather the desired Sail organization name using an interactive prompt.
     *
     * @return ?string
     */
    protected function gatherOrtanizationNameInteractively()
    {
        if ($this->organization) {
            return;
        }

        if (function_exists('\Laravel\Prompts\input')) {
            $this->organization = \Laravel\Prompts\text(
                label: 'What is the name of your repository organization?',
                default: 'reyemtech',
                required: true,
            );
        } else {
            $this->organization = $this->ask(
                'What is the name of your organization?',
                'reyemtech'
            );
        }

        return $this->organization;
    }

    /**
     * Build the Docker images.
     *
     * @return int Exit code (0 for success, non-zero for failure)
     */
    protected function buildDockerImages(string $environment, array $archs, string $repository): int
    {
        $this->output->writeln('');
        $this->components->info('🐳 Building Docker Images...');
        $this->output->writeln(' <fg=blue>=> Environment:</> '.$environment);
        $this->output->writeln(' <fg=blue>=> Architectures:</>');
        foreach ($archs as $arch) {
            $this->output->writeln('    <fg=green>-</> '.$arch);
        }
        $this->output->writeln('');

        // $commands = $this->buildCommands($archs, $environment, $repository);

        $removeVendorNodeModules = $this->removeVendorNodeModules ?? config('sail.build.remove_vendor_node_modules', true);

        $args = [
            'ARCHS' => $archs,
            'PUSH' => $this->push,
            'APP_NAME' => Str::slug(Config('app.name')),
            'VERSION' => config('sail.build.version', '1.0.0'),
            'APP_DIR' => realpath('.'),
            'RUNTIME_DIR' => realpath(InstalledVersions::getInstallPath('reyemtech/sail').'/runtimes/8.x'),
            'ORG' => $this->organization,
            'REMOVE_VENDOR_NODE_MODULES' => $removeVendorNodeModules ? 'true' : 'false',
        ];

        if ($this->useRepository) {
            $args['REGISTRY'] = $repository;
        }

        $commands = [];
        $path = realpath(InstalledVersions::getInstallPath('reyemtech/sail'));
        if (! is_dir("{$path}/certs")) {
            $commands[] = "{$path}/bin/sail-setup";
        }
        $commands[] = $this->createBakeCommand($args);

        return $this->runCommands($commands);
    }

    protected function createBakeCommand(array $args)
    {
        $bakeCommand = '';
        foreach ($args as $key => $value) {
            if (is_array($value)) {
                $bakeCommand .= ' '.$key.'='.implode(',', $value);
            } elseif (is_bool($value)) {
                // HCL requires "true" or "false" as strings for boolean variables
                $bakeCommand .= ' '.$key.'='.($value ? 'true' : 'false');
            } else {
                $bakeCommand .= ' '.$key.'='.$value;
            }
        }

        $bakeCommand .= ' docker buildx bake ';
        $bakeCommand .= ' -f '.realpath(InstalledVersions::getInstallPath('reyemtech/sail').'/runtimes/8.x/docker-bake.hcl');

        // $bakeCommand .= ' --print ';

        $bakeCommand .= $this->useRepository ? ' default' : ' app';

        $this->output->writeln(' <fg=blue>=> Build Command:</>');
        $this->output->writeln('    <fg=green>→</> '.$bakeCommand);
        $this->output->writeln('');
        $this->components->warn('⏳ This may take several minutes. Building in progress...');
        $this->output->writeln('');

        return $bakeCommand;
    }

    /**
     * Build the Docker images.
     *
     * @param  string  $environment
     * @param  array  $archs
     * @param  string  $repository
     * @return array
     */
    protected function buildCommands($archs, $environment, $repository)
    {
        $path = InstalledVersions::getInstallPath('reyemtech/sail');
        $dockerpath = $path.'/runtimes/8.x';

        if (! is_dir("{$path}/certs")) {
            $cmds[] = "{$path}/bin/sail-setup";
        }

        $baseCmd = "docker buildx build --build-context mainapp=. --build-context runtime={$dockerpath} -f {$dockerpath}/Dockerfile";
        $platformCmd = $baseCmd.' --platform '.implode(',', $archs);

        if ($this->push) {
            $baseCmd .= ' --push ';
        }

        if ($environment === 'production') {
            if ($this->push) {
                $platformCmd .= ' --push ';
            }
            $cmds[] = $platformCmd.' --target production '.$this->buildTags('web', $environment, $repository).' .';
            $cmds[] = $platformCmd.' --target worker'.$this->buildTags('worker', $environment, $repository).' .';
            if (! $this->push) {
                $cmds[] = "{$baseCmd} --target app --load ".$this->buildTags('web', $environment, $repository).' .';
                $cmds[] = "{$baseCmd} --target worker --load ".$this->buildTags('worker', $environment, $repository).' .';
            }
        } else {
            $cmds[] = $platformCmd.' --target app --load '.$this->buildTags('local', $environment, $repository).' .';
        }

        $this->output->writeln(' <fg=blue>=> Commands:</>');
        foreach ($cmds as $command) {
            $this->output->writeln('    <fg=green>-</> '.$command);
        }

        return $cmds;
    }

    protected function getConfig(bool $forceReuse = false)
    {
        $config = config('sail.build');
        if ($config['environments']) {
            $config['environments'] = explode(',', $config['environments']);
            $config['architectures'] = explode(',', $config['architectures']);
            $this->deploymentDomains = explode(',', config('sail.deploy.domains'));
            $this->output->writeln('');
            $this->output->writeln('<info>Previous build configuration found</info>');
            $this->output->writeln('');
            $this->output->writeln('<fg=yellow>==></> <fg=green>Environments:</>');
            foreach ($config['environments'] as $environment) {
                $this->output->writeln('    <fg=green>-</> '.$environment);
            }
            $this->output->writeln('<fg=yellow>==></> <fg=green>Architectures:</>');
            foreach ($config['architectures'] as $arch) {
                $this->output->writeln('    <fg=green>-</> '.$arch);
            }
            $this->output->writeln('<fg=yellow>==></> <fg=green>Domains:</>');
            foreach ($this->deploymentDomains as $domain) {
                $this->output->writeln('    <fg=green>-</> '.$domain);
            }
            $this->output->writeln('<fg=yellow>==></> <fg=green>Repository:</> '.$config['repository']);
            $this->output->writeln('<fg=yellow>==></> <fg=green>Organization:</> '.$config['organization']);
            $this->output->writeln('<fg=yellow>==></> <fg=green>Push:</> '.($config['push'] ? '<bg=green;fg-black> true </>' : '<bg=red;fg=black> false </>'));
            $this->output->writeln('<fg=yellow>==></> <fg=green>Version:</> '.$config['version']);
            $this->output->writeln('<fg=yellow>==></> <fg=green>Remove vendor/node_modules:</> '.($config['remove_vendor_node_modules'] ?? true ? '<bg=green;fg-black> true </>' : '<bg=red;fg=black> false </>'));

            if ($config['repository'] && $config['repository'] !== 'none') {
                $this->useRepository = true;
            }

            if ($config['push']) {
                $this->push = true;
            }

            if ($config['organization']) {
                $this->organization = $config['organization'];
            }

            if (isset($config['remove_vendor_node_modules'])) {
                $this->removeVendorNodeModules = (bool) $config['remove_vendor_node_modules'];
            }

            $this->reuseConfig = $forceReuse ? true : $this->reuseConfig;
            if (! $forceReuse) {
                if (function_exists('\Laravel\Prompts\confirm')) {
                    $this->reuseConfig = \Laravel\Prompts\confirm(
                        label: 'Would you like to build this configuration again?',
                        default: true,
                    );
                } else {
                    $this->reuseConfig = $this->confirm('Would you like to build this configuration again?', true);
                }
            }

            if ($this->reuseConfig) {
                $config['version'] = $this->getVersionChoice();
            }
        }

        return $this->reuseConfig ? $config : null;
    }

    protected function getVersionChoice()
    {
        $options = ['patch', 'minor', 'major', 'no'];
        $version = config('sail.build.version');

        if ($version === null) {
            return;
        }

        if (function_exists('\Laravel\Prompts\confirm')) {
            $increment = \Laravel\Prompts\select(
                label: 'Increment version?',
                options: $options,
                default: 'no',
            );
        } else {
            $increment = $this->choice('Increment version?', $options, 0, null, false);
        }

        if ($increment !== 'no') {
            $version = $this->bumpVersion($version, $increment);
            $this->output->writeln('<fg=yellow>==></> <fg=green>New Version:</> '.$version);
            $this->output->writeln('');
        }

        return $version;
    }

    /**
     * Bump the version number.
     */
    public function bumpVersion(string $version, string $type = 'patch'): string
    {
        [$major, $minor, $patch] = explode('.', $version);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $new = "{$major}.{$minor}.{$patch}";

        $writer = new Writer(base_path('.env'));
        $writer->set('SAIL_BUILD_VERSION', $new);
        $writer->write();

        Config::set('sail.build.version', $new);

        return $new;
    }

    protected function writeConfig($environments, $architectures, $repository)
    {
        $removeVendorNodeModules = $this->removeVendorNodeModules ?? config('sail.build.remove_vendor_node_modules', true);

        $config = [];
        $config['environments'] = implode(',', $environments);
        $config['architectures'] = implode(',', $architectures);
        $config['repository'] = $repository;
        $config['push'] = $this->push;
        $config['organization'] = $this->organization;
        $config['version'] = config('sail.build.version', '1.0.0');
        $config['remove_vendor_node_modules'] = $removeVendorNodeModules;

        $writer = new Writer(base_path('.env'));
        $writer->set('SAIL_BUILD_ENVIRONMENT', $config['environments'] ?? '');
        $writer->set('SAIL_BUILD_ARCHITECTURES', $config['architectures'] ?? '');
        $writer->set('SAIL_BUILD_REPOSITORY', $config['repository'] ?? '');
        $writer->set('SAIL_BUILD_PUSH', $config['push'] ?? 'false');
        $writer->set('SAIL_BUILD_ORGANIZATION', $config['organization'] ?? '');
        $writer->set('SAIL_BUILD_VERSION', $config['version'] ?? '1.0.0');
        $writer->set('SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES', $config['remove_vendor_node_modules'] ? 'true' : 'false');
        $writer->set('SAIL_DEPLOY_DOMAINS', implode(',', $this->deploymentDomains) ?? '');
        $writer->set('VITE_DEV_SERVER_URL', 'https://'.config('sail.domain').'/vite');
        $writer->write();

        Config::set('sail.build.environments', $config['environments'] ?? '');
        Config::set('sail.build.architectures', $config['architectures'] ?? '');
        Config::set('sail.build.repository', $config['repository']) ?? '';
        Config::set('sail.build.push', $config['push'] ? true : false);
        Config::set('sail.build.organization', $config['organization'] ?? '');
        Config::set('sail.build.version', $config['version'] ?? '1.0.0');
        Config::set('sail.build.remove_vendor_node_modules', $config['remove_vendor_node_modules']);
        Config::set('sail.deploy.domains', implode(',', $this->deploymentDomains) ?? '');
    }

    /**
     * Build the Docker tags.
     *
     * @param  string  $name
     * @param  string  $environment
     * @param  string  $repository
     * @return string
     */
    protected function buildTags($name, $environment, $repository)
    {
        $version = config('sail.build.version', '1.0.0');

        $tag = $this->useRepository && $repository !== 'none' ? $repository.'/' : '';
        $tag .= $this->useRepository && $repository !== 'none' && $this->organization ? $this->organization.'/' : '';
        $tag .= Str::slug(Config('app.name')).'-'.$name;

        $tags[] = "$tag:$environment";
        if ($environment === 'production') {
            $tags[] = "$tag:latest";
            $tags[] = "$tag:$version";
        }

        return ' --tag '.implode(' --tag ', $tags);
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @return int
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        $startTime = time();
        $lastProgress = 0;
        $hasError = false;
        $errorOutput = '';

        $exitCode = $process->run(function ($type, $line) use (&$lastProgress, $startTime, &$hasError, &$errorOutput) {
            $elapsed = time() - $startTime;

            // Capture error output - check for various error patterns
            $lineLower = strtolower($line);
            if ($type === Process::ERR ||
                stripos($line, 'ERROR') !== false ||
                stripos($line, 'error:') !== false ||
                stripos($line, 'failed to') !== false ||
                stripos($line, 'Invalid value') !== false ||
                stripos($line, 'failed to parse') !== false ||
                stripos($line, 'failed to solve') !== false) {
                $hasError = true;
                $errorOutput .= $line;
            }

            // Show progress indicator every 5 seconds
            if ($elapsed - $lastProgress >= 5) {
                $minutes = floor($elapsed / 60);
                $seconds = $elapsed % 60;
                $this->output->write(sprintf("\r  <fg=cyan>⏳ Building... (%dm %ds)</>", $minutes, $seconds));
                $lastProgress = $elapsed;
            }

            // Show important build output
            if (stripos($line, 'error') !== false || stripos($line, 'warning') !== false || stripos($line, '#') !== false || stripos($line, 'ERROR') !== false) {
                $this->output->writeln('');
                $this->output->write('    '.$line);
            }
        });

        // Check for errors in stderr as well
        $stderr = $process->getErrorOutput();
        $stdout = $process->getOutput();

        // Check both stdout and stderr for errors (docker-bake errors can appear in either)
        $allOutput = $stdout.$stderr;
        if (! empty($allOutput) && (
            stripos($allOutput, 'ERROR') !== false ||
            stripos($allOutput, 'error:') !== false ||
            stripos($allOutput, 'failed to solve') !== false ||
            stripos($allOutput, 'failed to parse') !== false ||
            stripos($allOutput, 'Invalid value') !== false
        )) {
            $hasError = true;
            $errorOutput .= $allOutput;
        }

        // If process failed or had errors, return non-zero
        if ($exitCode !== 0) {
            return $exitCode;
        }

        if ($hasError) {
            // Process returned 0 but had errors in output - this shouldn't happen but handle it
            $this->output->writeln('');
            $this->components->error('Build completed but errors were detected:');
            $this->output->writeln($errorOutput);

            return 1;
        }

        return $exitCode;
    }
}
