<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'sail:helm:validate')]
class HelmValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:helm:validate
                            {--chart-path= : Path to Helm chart directory (default: helm)}
                            {--strict : Fail on warnings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Helm chart templates locally';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $chartPath = $this->option('chart-path') ?: base_path('helm');
        $strict = $this->option('strict');

        if (! is_dir($chartPath)) {
            $this->components->error("Helm chart directory not found: {$chartPath}");
            $this->output->writeln('  Run <fg=cyan>php artisan sail:helm</> to generate the chart first.');

            return 1;
        }

        $this->output->writeln('');
        $this->components->info('🔍 Validating Helm Chart...');
        $this->output->writeln('  <fg=blue>Chart path:</> '.$chartPath);
        $this->output->writeln('');

        // Check if helm is installed
        $helmCheck = new Process(['helm', 'version', '--short']);
        $helmCheck->run();

        if (! $helmCheck->isSuccessful()) {
            $this->components->error('Helm is not installed or not in PATH');
            $this->output->writeln('  Install Helm: https://helm.sh/docs/intro/install/');

            return 1;
        }

        $errors = [];
        $warnings = [];

        // 1. Lint the chart
        $this->output->writeln('  <fg=blue>1.</> Running helm lint...');
        $lintProcess = new Process(['helm', 'lint', $chartPath]);
        $lintProcess->run();

        if (! $lintProcess->isSuccessful()) {
            $errors[] = 'Helm lint failed';
            $this->output->writeln('    <fg=red>✗</> Lint failed');
            $this->output->writeln('');
            $this->output->writeln($lintProcess->getErrorOutput());
        } else {
            $output = $lintProcess->getOutput();
            $this->output->writeln('    <fg=green>✓</> Lint passed');

            // Check for warnings
            if (stripos($output, 'warning') !== false || stripos($output, 'WARNING') !== false) {
                $warnings[] = 'Lint warnings found';
                $this->output->writeln('    <fg=yellow>⚠</> Warnings detected');
                if ($strict) {
                    $errors[] = 'Warnings found (strict mode)';
                }
            }
        }

        $this->output->writeln('');

        // 2. Template the chart with default values
        $this->output->writeln('  <fg=blue>2.</> Testing template rendering...');
        $templateProcess = new Process([
            'helm', 'template', 'test-release', $chartPath,
            '--namespace', 'default',
            '--kube-version', '1.34',
        ]);
        $templateProcess->run();

        if (! $templateProcess->isSuccessful()) {
            $errors[] = 'Template rendering failed';
            $this->output->writeln('    <fg=red>✗</> Template rendering failed');
            $this->output->writeln('');
            $errorOutput = $templateProcess->getErrorOutput();
            $this->output->writeln($errorOutput);

            // Try to extract helpful error messages
            if (preg_match('/Error: (.+)/', $errorOutput, $matches)) {
                $this->output->writeln('');
                $this->components->warn('💡 Error: '.$matches[1]);
            }
        } else {
            $this->output->writeln('    <fg=green>✓</> Templates rendered successfully');
            $output = $templateProcess->getOutput();

            // Count resources
            $resourceCount = substr_count($output, 'kind:');
            $this->output->writeln("    <fg=blue>→</> Generated {$resourceCount} Kubernetes resources");
        }

        $this->output->writeln('');

        // 3. Validate YAML syntax
        $this->output->writeln('  <fg=blue>3.</> Validating YAML syntax...');
        if ($templateProcess->isSuccessful()) {
            $yaml = $templateProcess->getOutput();
            $resources = explode('---', $yaml);
            $yamlErrors = 0;

            foreach ($resources as $resource) {
                $resource = trim($resource);
                if (empty($resource)) {
                    continue;
                }

                // Try to parse YAML using Symfony YAML component
                try {
                    Yaml::parse($resource);
                } catch (\Exception $e) {
                    $yamlErrors++;
                }
            }

            if ($yamlErrors > 0) {
                $errors[] = "YAML syntax errors found ({$yamlErrors} resources)";
                $this->output->writeln("    <fg=red>✗</> {$yamlErrors} YAML syntax errors found");
            } else {
                $this->output->writeln('    <fg=green>✓</> YAML syntax valid');
            }
        } else {
            $this->output->writeln('    <fg=yellow>⚠</> Skipped (template rendering failed)');
        }

        $this->output->writeln('');

        // Summary
        if (empty($errors)) {
            $this->components->info('✅ Helm chart validation passed!');
            if (! empty($warnings)) {
                $this->output->writeln('');
                $this->components->warn('⚠️  Warnings detected (non-blocking)');
            }
            $this->output->writeln('');

            return 0;
        } else {
            $this->components->error('❌ Helm chart validation failed!');
            $this->output->writeln('');
            $this->components->warn('Errors found:');
            foreach ($errors as $error) {
                $this->output->writeln("  <fg=red>✗</> {$error}");
            }
            $this->output->writeln('');
            $this->output->writeln('  <fg=blue>💡 Tip:</> Run <fg=cyan>php artisan sail:helm</> to regenerate the chart from stubs.');
            $this->output->writeln('');

            return 1;
        }
    }
}
