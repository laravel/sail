<?php

namespace Laravel\Sail\Console\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithHelm
{
    protected string $projectName;

    protected string $helmPath;

    /**
     * Build the Docker Compose file.
     *
     * @param  array  $services
     * @return void
     */
    protected function buildHelm()
    {
        $this->output->writeln('');
        $this->output->writeln('<info>==> Preparing Helm Chart...</info>');
        $this->output->writeln('');
        $this->projectName = config('app.name', 'laravel');
        $this->createHelmDirectory();
        $this->buildHelmChart();
        $this->buildHelmValues();
        $this->output->writeln('<info>==> Helm Chart Created Successfully!! <==</info>');
        $this->output->writeln('');
    }

    /**
     * Create the Helm Chart directory.
     *
     * @return string
     */
    protected function createHelmDirectory(): string
    {
        $this->helmPath = base_path('helm');

        $stubPath = realpath(__DIR__ . '/../../../stubs/helm');
        $chart = $this->copyFiles($stubPath, $this->helmPath);

        $templatePath = realpath($stubPath . '/templates');
        $installTemplatePath = $this->helmPath . '/templates';
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

            $sourceFile = $path_from . '/' . $file;
            $destinationFile = $path_to . '/' . str_replace('.stub', '.yaml', $file);

            if (is_file($sourceFile)) {
                if ($file !== 'values.stub') {
                    if (!is_file($destinationFile) || hash_file('sha256', $sourceFile) !== hash_file('sha256', $destinationFile)) {
                        copy($sourceFile, $destinationFile);
                        $changes = true;
                    }
                } elseif (!is_file($destinationFile)) {
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
     * @return void
     */
    protected function buildHelmChart()
    {
        $this->output->writeln('  <bg=blue;fg=black> INFO </> Updating Chart.yaml...');

        $chartPath = $this->helmPath . '/Chart.yaml';
        $chart = Yaml::parseFile($chartPath);
        $chart['name'] = $this->projectName;
        $chart['version'] = config('sail.build.version', '1.0.0');
        $chart['appVersion'] = config('sail.build.version', '1.0.0');
        $chart['description'] = $this->projectName . ' Helm Chart';
        $chart['maintainers'] = [
            [
                'name' => config('sail.build.organization', 'Laravel Sail'),
                'email' => config('mail.from.address', 'example@example.com')
            ]
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
        $valuesPath = $this->helmPath . '/Values.yaml';
        $values = Yaml::parseFile($valuesPath);

        $values['name'] = $this->projectName;

        $values['ingress']['hosts'][0]['host'] = config('sail.domain', "{$this->projectName}.test");

        $repository = config('sail.build.repository') ? config('sail.build.repository') . '/' : '';
        $repository .= config('sail.build.organization') ? config('sail.build.organization') . '/' : '';
        $repository .= Str::lower($this->projectName);

        $values['global']['tag'] = config('sail.build.version', 'latest');
        $values['web']['image']['repository'] = "{$repository}-web";
        $values['worker']['image']['repository'] = "{$repository}-worker";

        $values['secret']['path'] = config('sail.secret.path', 'secret/laravel/production');
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

        $yaml = Yaml::dump($values, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($valuesPath, $yaml);
        $this->output->writeln('');
    }
}
