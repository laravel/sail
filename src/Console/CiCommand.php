<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:ci')]
class CiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:ci
                            {--provider= : CI provider (github-actions, circleci, travis)}
                            {--overwrite : Overwrite existing CI configuration files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add CI/CD configuration files for automated Docker builds';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->output->writeln('');
        $this->components->info('🚀 Setting up CI/CD for Docker builds...');
        $this->output->writeln('');

        $provider = $this->option('provider');

        if (! $provider) {
            $providers = [
                'github-actions' => 'GitHub Actions',
                'gitlab-ci' => 'GitLab CI/CD',
                'azure-devops' => 'Azure DevOps Pipelines',
                'circleci' => 'CircleCI',
                'aws-codebuild' => 'AWS CodeBuild',
                'travis' => 'Travis CI',
            ];

            if (function_exists('\Laravel\Prompts\select')) {
                $provider = \Laravel\Prompts\select(
                    label: 'Which CI provider would you like to use?',
                    options: $providers,
                    default: 'github-actions',
                );
            } else {
                $provider = $this->choice('Which CI provider would you like to use?', $providers, 0);
            }
        }

        $provider = strtolower($provider);

        $validProviders = ['github-actions', 'gitlab-ci', 'azure-devops', 'circleci', 'aws-codebuild', 'travis'];
        if (! in_array($provider, $validProviders)) {
            $this->components->error('Invalid CI provider. Valid options: '.implode(', ', $validProviders));

            return 1;
        }

        $overwrite = $this->option('overwrite');

        return match ($provider) {
            'github-actions' => $this->setupGitHubActions($overwrite),
            'gitlab-ci' => $this->setupGitLabCI($overwrite),
            'azure-devops' => $this->setupAzureDevOps($overwrite),
            'circleci' => $this->setupCircleCI($overwrite),
            'aws-codebuild' => $this->setupAWSCodeBuild($overwrite),
            'travis' => $this->setupTravisCI($overwrite),
            default => 1,
        };
    }

    /**
     * Setup GitHub Actions workflow.
     */
    protected function setupGitHubActions(bool $overwrite): int
    {
        $workflowDir = base_path('.github/workflows');
        $workflowFile = $workflowDir.'/build.yml';

        if (file_exists($workflowFile) && ! $overwrite) {
            $this->components->error('GitHub Actions workflow already exists at: '.$workflowFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        if (! is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        $stubPath = __DIR__.'/../../stubs/ci/github-actions-build.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('{{APP_NAME}}', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('{{REPOSITORY}}', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('{{ORGANIZATION}}', config('sail.build.organization', 'reyemtech'), $stub);

        file_put_contents($workflowFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created GitHub Actions workflow: '.$workflowFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following secrets to your GitHub repository:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_ACCESS_KEY_ID');
            $this->output->writeln('     - AWS_SECRET_ACCESS_KEY');
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CREDENTIALS (JSON with service principal)');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME (or GHCR_IO_USERNAME for GitHub Container Registry)');
            $this->output->writeln('     - REGISTRY_PASSWORD (or GHCR_IO_PASSWORD for GitHub Container Registry)');
        }
        $this->output->writeln('  2. Customize the workflow file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup CircleCI configuration.
     */
    protected function setupCircleCI(bool $overwrite): int
    {
        $configFile = base_path('.circleci/config.yml');

        if (file_exists($configFile) && ! $overwrite) {
            $this->components->error('CircleCI config already exists at: '.$configFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        if (! is_dir(dirname($configFile))) {
            mkdir(dirname($configFile), 0755, true);
        }

        $stubPath = __DIR__.'/../../stubs/ci/circleci-config.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('{{APP_NAME}}', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('{{REPOSITORY}}', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('{{ORGANIZATION}}', config('sail.build.organization', 'reyemtech'), $stub);

        file_put_contents($configFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created CircleCI config: '.$configFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following environment variables in CircleCI project settings:');
        $this->output->writeln('     - REGISTRY_USERNAME (or GHCR_IO_USERNAME for GitHub Container Registry)');
        $this->output->writeln('     - REGISTRY_PASSWORD (or GHCR_IO_PASSWORD for GitHub Container Registry)');
        $this->output->writeln('  2. Customize the config file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup Travis CI configuration.
     */
    protected function setupTravisCI(bool $overwrite): int
    {
        $configFile = base_path('.travis.yml');

        if (file_exists($configFile) && ! $overwrite) {
            $this->components->error('Travis CI config already exists at: '.$configFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        $stubPath = __DIR__.'/../../stubs/ci/travis-ci.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('{{APP_NAME}}', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('{{REPOSITORY}}', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('{{ORGANIZATION}}', config('sail.build.organization', 'reyemtech'), $stub);

        file_put_contents($configFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created Travis CI config: '.$configFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following environment variables in Travis CI project settings:');
        $this->output->writeln('     - REGISTRY_USERNAME (or GHCR_IO_USERNAME for GitHub Container Registry)');
        $this->output->writeln('     - REGISTRY_PASSWORD (or GHCR_IO_PASSWORD for GitHub Container Registry)');
        $this->output->writeln('  2. Customize the config file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup GitLab CI configuration.
     */
    protected function setupGitLabCI(bool $overwrite): int
    {
        $configFile = base_path('.gitlab-ci.yml');

        if (file_exists($configFile) && ! $overwrite) {
            $this->components->error('GitLab CI config already exists at: '.$configFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        $stubPath = __DIR__.'/../../stubs/ci/gitlab-ci.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('{{APP_NAME}}', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('{{REPOSITORY}}', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('{{ORGANIZATION}}', config('sail.build.organization', 'reyemtech'), $stub);

        file_put_contents($configFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created GitLab CI config: '.$configFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following CI/CD variables in GitLab project settings:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_ACCESS_KEY_ID');
            $this->output->writeln('     - AWS_SECRET_ACCESS_KEY');
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CLIENT_ID');
            $this->output->writeln('     - AZURE_CLIENT_SECRET');
            $this->output->writeln('     - AZURE_TENANT_ID');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME (or CI_REGISTRY_USER for GitLab Container Registry)');
            $this->output->writeln('     - REGISTRY_PASSWORD (or CI_REGISTRY_PASSWORD for GitLab Container Registry)');
        }
        $this->output->writeln('  2. Customize the config file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup Azure DevOps Pipelines configuration.
     */
    protected function setupAzureDevOps(bool $overwrite): int
    {
        $pipelineDir = base_path('azure-pipelines');
        $pipelineFile = $pipelineDir.'/build.yml';

        if (file_exists($pipelineFile) && ! $overwrite) {
            $this->components->error('Azure DevOps pipeline already exists at: '.$pipelineFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        if (! is_dir($pipelineDir)) {
            mkdir($pipelineDir, 0755, true);
        }

        $stubPath = __DIR__.'/../../stubs/ci/azure-pipelines.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('{{APP_NAME}}', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('{{REPOSITORY}}', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('{{ORGANIZATION}}', config('sail.build.organization', 'reyemtech'), $stub);

        file_put_contents($pipelineFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created Azure DevOps pipeline: '.$pipelineFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following variables in Azure DevOps pipeline settings:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_ACCESS_KEY_ID');
            $this->output->writeln('     - AWS_SECRET_ACCESS_KEY');
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CREDENTIALS (service connection)');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME');
            $this->output->writeln('     - REGISTRY_PASSWORD');
        }
        $this->output->writeln('  2. Create a pipeline in Azure DevOps and point it to: azure-pipelines/build.yml');
        $this->output->writeln('  3. Customize the pipeline file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup AWS CodeBuild configuration.
     */
    protected function setupAWSCodeBuild(bool $overwrite): int
    {
        $buildspecFile = base_path('buildspec.yml');

        if (file_exists($buildspecFile) && ! $overwrite) {
            $this->components->error('AWS CodeBuild buildspec already exists at: '.$buildspecFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        $stubPath = __DIR__.'/../../stubs/ci/buildspec.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('{{APP_NAME}}', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('{{REPOSITORY}}', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('{{ORGANIZATION}}', config('sail.build.organization', 'reyemtech'), $stub);

        file_put_contents($buildspecFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created AWS CodeBuild buildspec: '.$buildspecFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Create a CodeBuild project in AWS Console');
        $this->output->writeln('  2. Set the buildspec file to: buildspec.yml');
        $this->output->writeln('  3. Configure environment variables in CodeBuild project:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
            $this->output->writeln('     Note: ECR authentication uses IAM role attached to CodeBuild project');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CLIENT_ID');
            $this->output->writeln('     - AZURE_CLIENT_SECRET');
            $this->output->writeln('     - AZURE_TENANT_ID');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME');
            $this->output->writeln('     - REGISTRY_PASSWORD');
        }
        $this->output->writeln('  4. Customize the buildspec file if needed');
        $this->output->writeln('');

        return 0;
    }
}
