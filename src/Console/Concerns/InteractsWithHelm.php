<?php

namespace Laravel\Sail\Console\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithHelm
{
    protected string $projectName;

    protected string $helmPath;

    /**
     * Build the Docker Compose file.
     *
     * @param  bool  $updateVersion  Whether to update the chart version (default: true)
     * @return void
     */
    protected function buildHelm(bool $updateVersion = true)
    {
        $this->output->writeln('');
        $this->components->info('📦 Preparing Helm Chart...');
        $this->output->writeln('');
        $this->projectName = config('app.name', 'laravel');
        $this->createHelmDirectory();

        // Update chart (version update controlled by parameter)
        $this->buildHelmChart($updateVersion);

        $this->buildHelmValues();

        // Validate Helm chart
        $this->validateHelmChart();

        $this->output->writeln('');
        $this->components->info('✅ Helm Chart Created Successfully!');
        $this->output->writeln('');
    }

    /**
     * Validate the Helm chart using helm lint.
     */
    protected function validateHelmChart(): void
    {
        $this->output->writeln('  <bg=blue;fg=black> INFO </> Validating Helm chart...');

        $process = new Process(['helm', 'lint', $this->helmPath]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->output->writeln('');
            $this->components->error('Helm chart validation failed!');
            $this->output->writeln($process->getErrorOutput());
            $this->components->warn('💡 Tip: Review the errors above and fix any issues in your Helm templates.');
            $this->output->writeln('');
        } else {
            $this->output->writeln('  <fg=green>✓</> Helm chart validation passed');
        }
    }

    /**
     * Create the Helm Chart directory.
     */
    protected function createHelmDirectory(): string
    {
        $this->helmPath = base_path('helm');

        $stubPath = realpath(__DIR__.'/../../../stubs/helm');
        $chart = $this->copyFiles($stubPath, $this->helmPath);

        $templatePath = realpath($stubPath.'/templates');
        $installTemplatePath = $this->helmPath.'/templates';
        $templates = $this->copyFiles($templatePath, $installTemplatePath);

        if ($chart || $templates) {
            $this->output->writeln('  <bg=blue;fg=black> INFO </> Helm Chart files copied successfully.');
        } else {
            $this->output->writeln('  <bg=blue;fg=black> INFO </> No changes made to the Helm Chart files.');
        }
        $this->output->writeln('');

        return $this->helmPath;
    }

    protected function copyFiles(string $path_from, string $path_to): bool
    {
        $changes = false;

        if (! is_dir($path_to)) {
            mkdir($path_to, 0755, true);
        }

        $files = scandir($path_from);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourceFile = $path_from.'/'.$file;
            $destinationFile = $path_to.'/'.str_replace('.stub', '.yaml', $file);

            if (is_file($sourceFile)) {
                if ($file !== 'values.stub') {
                    if (! is_file($destinationFile) || hash_file('sha256', $sourceFile) !== hash_file('sha256', $destinationFile)) {
                        copy($sourceFile, $destinationFile);
                        $changes = true;
                    }
                } elseif (! is_file($destinationFile)) {
                    copy($sourceFile, $destinationFile);
                    $changes = true;
                }
            }
        }

        return $changes;
    }

    /**
     * Build the Helm Chart.
     *
     * @param  bool  $updateVersion  Whether to update the version (default: true)
     * @return void
     */
    protected function buildHelmChart(bool $updateVersion = true)
    {
        $this->output->writeln('  <bg=blue;fg=black> INFO </> Updating Chart.yaml...');

        $chartPath = $this->helmPath.'/Chart.yaml';
        $chart = Yaml::parseFile($chartPath);
        $chart['name'] = $this->projectName;

        // Only update version if requested (preserve if already updated before build)
        if ($updateVersion) {
            $chart['version'] = config('sail.build.version', '1.0.0');
            $chart['appVersion'] = config('sail.build.version', '1.0.0');
        }

        $chart['description'] = $this->projectName.' Helm Chart';
        $chart['maintainers'] = [
            [
                'name' => config('sail.build.organization', 'Laravel Sail'),
                'email' => config('mail.from.address', 'example@example.com'),
            ],
        ];

        $yaml = Yaml::dump($chart, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($chartPath, $yaml);
        $this->output->writeln('');
    }

    /**
     * Build the Helm Values.yaml file.
     *
     * @return void
     */
    protected function buildHelmValues()
    {
        $this->output->writeln('  <bg=blue;fg=black> INFO </> Updating Values.yaml...');
        $valuesPath = $this->helmPath.'/Values.yaml';
        $values = Yaml::parseFile($valuesPath);

        $values['name'] = $this->projectName;

        $values['ingress']['hosts'][0]['host'] = config('sail.domain', "{$this->projectName}.test");

        $repository = config('sail.build.repository') ? config('sail.build.repository').'/' : '';
        $repository .= config('sail.build.organization') ? config('sail.build.organization').'/' : '';
        $repository .= Str::lower($this->projectName);

        $values['global']['tag'] = config('sail.build.version', 'latest');
        $values['web']['image']['repository'] = "{$repository}-web";
        $values['worker']['image']['repository'] = "{$repository}-worker";

        $values['secret']['path'] = config('sail.secret.path', "secret/laravel/{$this->projectName}");
        $values['secret']['store'] = config('sail.secret.store', 'vault-backend');

        $domains = explode(',', config('sail.deploy.domains', 'reyemtech.com'));
        foreach ($domains as $key => $domain) {
            $values['ingress']['hosts'][$key]['host'] = $domain;
            $values['ingress']['hosts'][$key]['paths'] = [
                [
                    'path' => '/',
                    'pathType' => 'Prefix',
                ],
            ];
        }

        // Add resource recommendations if not set
        if (empty($values['resources']) || (empty($values['resources']['requests']) && empty($values['resources']['limits']))) {
            $this->output->writeln('  <bg=yellow;fg=black> WARN </> No resource limits configured. Recommending defaults...');
            $recommendations = $this->recommendResources();
            $values['resources'] = $recommendations;
            $this->output->writeln('  <fg=green>✓</> Applied resource recommendations');
            $this->output->writeln('    Requests: CPU='.$recommendations['requests']['cpu'].', Memory='.$recommendations['requests']['memory']);
            $this->output->writeln('    Limits: CPU='.$recommendations['limits']['cpu'].', Memory='.$recommendations['limits']['memory']);
        }

        $yaml = Yaml::dump($values, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($valuesPath, $yaml);
        $this->output->writeln('');
    }

    /**
     * Recommend resource values based on app characteristics.
     */
    protected function recommendResources(): array
    {
        $appPath = base_path();
        $vendorSize = 0;
        $hasHorizon = file_exists($appPath.'/app/Console/Commands/HorizonCommand.php') ||
                      file_exists($appPath.'/config/horizon.php');

        // Estimate app size based on vendor directory
        if (is_dir($appPath.'/vendor')) {
            $vendorSize = $this->getDirectorySize($appPath.'/vendor');
        }

        // Base recommendations
        $cpuRequest = '100m';
        $memoryRequest = '256Mi';
        $cpuLimit = '500m';
        $memoryLimit = '512Mi';

        // Adjust based on app size
        if ($vendorSize > 100 * 1024 * 1024) { // > 100MB
            $memoryRequest = '512Mi';
            $memoryLimit = '1Gi';
        }

        // Adjust for Horizon (queue workers need more resources)
        if ($hasHorizon) {
            $cpuLimit = '1000m';
            $memoryLimit = '1Gi';
        }

        return [
            'requests' => [
                'cpu' => $cpuRequest,
                'memory' => $memoryRequest,
            ],
            'limits' => [
                'cpu' => $cpuLimit,
                'memory' => $memoryLimit,
            ],
        ];
    }

    /**
     * Get directory size in bytes.
     */
    protected function getDirectorySize(string $directory): int
    {
        $size = 0;
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }

        return $size;
    }
}
