<?php

namespace Laravel\Sail\Console\Concerns;

use Symfony\Component\Process\Process;

trait InteractsWithDockerRegistry
{
    /**
     * Check if user is logged in to the Docker registry.
     *
     * @param  string  $registry  Registry host (e.g., 'ghcr.io', 'docker.io')
     * @return bool True if logged in, false otherwise
     */
    protected function checkRegistryLogin(string $registry): bool
    {
        // Special handling for ECR and ACR
        if ($this->isECRRegistry($registry)) {
            return $this->checkECRLogin($registry);
        }

        if ($this->isACRRegistry($registry)) {
            return $this->checkACRLogin($registry);
        }

        // Check Docker config file for authentication
        $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: getenv('HOMEDRIVE').getenv('HOMEPATH'));
        $dockerConfigPath = getenv('DOCKER_CONFIG') ?: ($homeDir.'/.docker/config.json');

        if (! file_exists($dockerConfigPath)) {
            return false;
        }

        $config = json_decode(file_get_contents($dockerConfigPath), true);

        if (! isset($config['auths']) || ! is_array($config['auths'])) {
            return false;
        }

        // Normalize registry for comparison
        $registryNormalized = $this->normalizeRegistryForCheck($registry);

        // Check if registry is in auths
        foreach ($config['auths'] as $host => $auth) {
            $hostNormalized = $this->normalizeRegistryForCheck($host);

            // Direct match
            if ($hostNormalized === $registryNormalized) {
                return isset($auth['auth']) && ! empty($auth['auth']);
            }

            // Check if registry is a substring match (e.g., 'ghcr.io' matches 'https://ghcr.io')
            if (strpos($hostNormalized, $registryNormalized) !== false ||
                strpos($registryNormalized, $hostNormalized) !== false) {
                return isset($auth['auth']) && ! empty($auth['auth']);
            }
        }

        return false;
    }

    /**
     * Check if registry is Amazon ECR.
     *
     * @param  string  $registry  Registry host
     */
    protected function isECRRegistry(string $registry): bool
    {
        return $registry === 'ecr' ||
               strpos($registry, '.dkr.ecr.') !== false ||
               strpos($registry, 'amazonaws.com') !== false ||
               preg_match('/\d+\.dkr\.ecr\.[^.]+\.amazonaws\.com/', $registry);
    }

    /**
     * Check if registry is Azure ACR.
     *
     * @param  string  $registry  Registry host
     */
    protected function isACRRegistry(string $registry): bool
    {
        return $registry === 'azurecr' ||
               strpos($registry, '.azurecr.io') !== false;
    }

    /**
     * Check ECR login status by verifying AWS CLI and Docker config.
     *
     * @param  string  $registry  ECR registry URL
     */
    protected function checkECRLogin(string $registry): bool
    {
        // Check if AWS CLI is available
        $awsProcess = new Process(['aws', '--version']);
        $awsProcess->run();

        if (! $awsProcess->isSuccessful()) {
            return false;
        }

        // Try to get ECR login token (this will fail if not authenticated)
        // Extract region from registry if it's a full ECR URL
        $region = $this->extractECRRegion($registry);

        if ($region) {
            $ecrProcess = new Process(['aws', 'ecr', 'get-login-password', '--region', $region]);
            $ecrProcess->run();

            return $ecrProcess->isSuccessful();
        }

        // If we can't extract region, check Docker config
        $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: getenv('HOMEDRIVE').getenv('HOMEPATH'));
        $dockerConfigPath = getenv('DOCKER_CONFIG') ?: ($homeDir.'/.docker/config.json');

        if (file_exists($dockerConfigPath)) {
            $config = json_decode(file_get_contents($dockerConfigPath), true);
            if (isset($config['auths'])) {
                foreach ($config['auths'] as $host => $auth) {
                    if (strpos($host, '.dkr.ecr.') !== false || strpos($host, 'amazonaws.com') !== false) {
                        return isset($auth['auth']) && ! empty($auth['auth']);
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check ACR login status by verifying Azure CLI.
     *
     * @param  string  $registry  ACR registry URL
     */
    protected function checkACRLogin(string $registry): bool
    {
        // Check if Azure CLI is available
        $azProcess = new Process(['az', '--version']);
        $azProcess->run();

        if (! $azProcess->isSuccessful()) {
            return false;
        }

        // Extract registry name from URL (e.g., "myregistry.azurecr.io" -> "myregistry")
        $registryName = $this->extractACRName($registry);

        if ($registryName) {
            // Check if logged in by trying to list repositories
            $acrProcess = new Process(['az', 'acr', 'repository', 'list', '--name', $registryName, '--output', 'none']);
            $acrProcess->run();

            return $acrProcess->isSuccessful();
        }

        // Fallback: check Docker config
        $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: getenv('HOMEDRIVE').getenv('HOMEPATH'));
        $dockerConfigPath = getenv('DOCKER_CONFIG') ?: ($homeDir.'/.docker/config.json');

        if (file_exists($dockerConfigPath)) {
            $config = json_decode(file_get_contents($dockerConfigPath), true);
            if (isset($config['auths'])) {
                foreach ($config['auths'] as $host => $auth) {
                    if (strpos($host, '.azurecr.io') !== false) {
                        return isset($auth['auth']) && ! empty($auth['auth']);
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract AWS region from ECR registry URL.
     *
     * @param  string  $registry  ECR registry URL
     * @return string|null Region or null if not found
     */
    protected function extractECRRegion(string $registry): ?string
    {
        // Pattern: {account-id}.dkr.ecr.{region}.amazonaws.com
        if (preg_match('/\.dkr\.ecr\.([^.]+)\.amazonaws\.com/', $registry, $matches)) {
            return $matches[1];
        }

        // Try to get from AWS config or environment
        $region = getenv('AWS_DEFAULT_REGION') ?: getenv('AWS_REGION');
        if ($region) {
            return $region;
        }

        return null;
    }

    /**
     * Extract ACR registry name from URL.
     *
     * @param  string  $registry  ACR registry URL
     * @return string|null Registry name or null if not found
     */
    protected function extractACRName(string $registry): ?string
    {
        // Pattern: {name}.azurecr.io
        if (preg_match('/([^.]+)\.azurecr\.io/', $registry, $matches)) {
            return $matches[1];
        }

        // If just "azurecr", try to get from environment or config
        if ($registry === 'azurecr') {
            $acrName = getenv('AZURE_ACR_NAME');
            if ($acrName) {
                return $acrName;
            }
        }

        return null;
    }

    /**
     * Normalize registry host name for comparison.
     *
     * @param  string  $registry  Registry host
     * @return string Normalized registry host (for docker login command)
     */
    protected function normalizeRegistryHost(string $registry): string
    {
        // Remove protocol if present
        $registry = preg_replace('#^https?://#', '', $registry);

        // Remove trailing slash
        $registry = rtrim($registry, '/');

        return $registry;
    }

    /**
     * Normalize registry for checking in Docker config.
     *
     * @param  string  $registry  Registry host
     * @return string Normalized registry host for comparison
     */
    protected function normalizeRegistryForCheck(string $registry): string
    {
        // Remove protocol if present
        $registry = preg_replace('#^https?://#', '', $registry);

        // Remove trailing slash and path
        $registry = rtrim($registry, '/');
        $registry = preg_replace('#/.*$#', '', $registry);

        // Convert to lowercase for comparison
        $registry = strtolower($registry);

        // Handle special cases - Docker Hub can be stored as 'https://index.docker.io/v1/' or 'docker.io'
        if ($registry === 'docker.io' || $registry === 'index.docker.io') {
            return 'docker.io';
        }

        return $registry;
    }

    /**
     * Login to Docker registry.
     *
     * @param  string  $registry  Registry host
     * @return bool True if login successful, false otherwise
     */
    protected function loginToRegistry(string $registry): bool
    {
        $this->output->writeln('');
        $this->components->info('🔐 Logging in to registry: '.$registry);
        $this->output->writeln('');

        // Special handling for ECR
        if ($this->isECRRegistry($registry)) {
            return $this->loginToECR($registry);
        }

        // Special handling for ACR
        if ($this->isACRRegistry($registry)) {
            return $this->loginToACR($registry);
        }

        // Standard Docker registry login
        $username = null;
        $password = null;

        // Try to get from environment variables first
        $registryEnv = strtoupper(str_replace(['.', '-'], '_', $registry));
        $envUsername = $registryEnv.'_USERNAME';
        $envPassword = $registryEnv.'_PASSWORD';

        if (getenv($envUsername) && getenv($envPassword)) {
            $username = getenv($envUsername);
            $password = getenv($envPassword);
            $this->output->writeln('  <fg=blue>Using credentials from environment variables</>');
        } else {
            // Prompt for credentials
            if (function_exists('\Laravel\Prompts\text')) {
                $username = \Laravel\Prompts\text(
                    label: 'Username (or token name for GHCR):',
                    required: true,
                );
                $password = \Laravel\Prompts\password(
                    label: 'Password (or token for GHCR):',
                    required: true,
                );
            } else {
                $username = $this->ask('Username (or token name for GHCR):');
                $password = $this->secret('Password (or token for GHCR):');
            }
        }

        if (empty($username) || empty($password)) {
            $this->components->error('Username and password are required');

            return false;
        }

        // Build docker login command
        $process = new Process(['docker', 'login', $registry, '--username', $username, '--password-stdin']);
        $process->setInput($password);
        $process->setTimeout(60);

        $process->run(function ($type, $buffer) {
            // Show login progress but suppress sensitive info
            if (stripos($buffer, 'password') === false && stripos($buffer, 'token') === false) {
                if ($type === Process::OUT) {
                    $this->output->write($buffer);
                }
            }
        });

        if ($process->isSuccessful()) {
            $this->output->writeln('  <fg=green>✓</> Successfully logged in to '.$registry);
            $this->output->writeln('');

            return true;
        } else {
            $this->output->writeln('');
            $this->components->error('Login failed: '.$process->getErrorOutput());
            $this->output->writeln('');

            return false;
        }
    }

    /**
     * Login to Amazon ECR.
     *
     * @param  string  $registry  ECR registry URL or 'ecr'
     */
    protected function loginToECR(string $registry): bool
    {
        // Check if AWS CLI is available
        $awsProcess = new Process(['aws', '--version']);
        $awsProcess->run();

        if (! $awsProcess->isSuccessful()) {
            $this->components->error('AWS CLI is not installed or not in PATH');
            $this->output->writeln('  Install AWS CLI: https://aws.amazon.com/cli/');

            return false;
        }

        // Extract region
        $region = $this->extractECRRegion($registry);

        if (! $region) {
            // Prompt for region if not found
            if (function_exists('\Laravel\Prompts\text')) {
                $region = \Laravel\Prompts\text(
                    label: 'AWS Region (e.g., us-east-1):',
                    required: true,
                );
            } else {
                $region = $this->ask('AWS Region (e.g., us-east-1):');
            }
        }

        if (empty($region)) {
            $this->components->error('AWS region is required for ECR login');

            return false;
        }

        $this->output->writeln('  <fg=blue>Getting ECR login token...</>');

        // Get ECR login password
        $ecrProcess = new Process(['aws', 'ecr', 'get-login-password', '--region', $region]);
        $ecrProcess->run();

        if (! $ecrProcess->isSuccessful()) {
            $this->output->writeln('');
            $this->components->error('Failed to get ECR login token: '.$ecrProcess->getErrorOutput());
            $this->output->writeln('  Make sure AWS credentials are configured (aws configure)');
            $this->output->writeln('');

            return false;
        }

        $password = trim($ecrProcess->getOutput());

        // Extract registry URL if we only have 'ecr'
        $ecrRegistry = $registry;
        if ($registry === 'ecr') {
            // Try to get from environment or construct from account ID
            $accountId = getenv('AWS_ACCOUNT_ID');
            if ($accountId) {
                $ecrRegistry = "{$accountId}.dkr.ecr.{$region}.amazonaws.com";
            } else {
                // Try to get account ID from AWS
                $accountProcess = new Process(['aws', 'sts', 'get-caller-identity', '--query', 'Account', '--output', 'text']);
                $accountProcess->run();
                if ($accountProcess->isSuccessful()) {
                    $accountId = trim($accountProcess->getOutput());
                    $ecrRegistry = "{$accountId}.dkr.ecr.{$region}.amazonaws.com";
                } else {
                    $this->components->error('Could not determine ECR registry URL. Please provide full registry URL.');

                    return false;
                }
            }
        }

        // Login to Docker with ECR token
        $loginProcess = new Process(['docker', 'login', '--username', 'AWS', '--password-stdin', $ecrRegistry]);
        $loginProcess->setInput($password);
        $loginProcess->setTimeout(60);

        $loginProcess->run(function ($type, $buffer) {
            if (stripos($buffer, 'password') === false && stripos($buffer, 'token') === false) {
                if ($type === Process::OUT) {
                    $this->output->write($buffer);
                }
            }
        });

        if ($loginProcess->isSuccessful()) {
            $this->output->writeln('  <fg=green>✓</> Successfully logged in to ECR ('.$ecrRegistry.')');
            $this->output->writeln('');

            return true;
        } else {
            $this->output->writeln('');
            $this->components->error('ECR login failed: '.$loginProcess->getErrorOutput());
            $this->output->writeln('');

            return false;
        }
    }

    /**
     * Login to Azure Container Registry.
     *
     * @param  string  $registry  ACR registry URL or 'azurecr'
     */
    protected function loginToACR(string $registry): bool
    {
        // Check if Azure CLI is available
        $azProcess = new Process(['az', '--version']);
        $azProcess->run();

        if (! $azProcess->isSuccessful()) {
            $this->components->error('Azure CLI is not installed or not in PATH');
            $this->output->writeln('  Install Azure CLI: https://docs.microsoft.com/en-us/cli/azure/install-azure-cli');

            return false;
        }

        // Extract registry name
        $registryName = $this->extractACRName($registry);

        if (! $registryName) {
            // Prompt for registry name if not found
            if (function_exists('\Laravel\Prompts\text')) {
                $registryName = \Laravel\Prompts\text(
                    label: 'ACR Registry Name (e.g., myregistry):',
                    required: true,
                );
            } else {
                $registryName = $this->ask('ACR Registry Name (e.g., myregistry):');
            }
        }

        if (empty($registryName)) {
            $this->components->error('ACR registry name is required');

            return false;
        }

        $this->output->writeln('  <fg=blue>Logging in to ACR using Azure CLI...</>');

        // Use az acr login (handles authentication automatically)
        $loginProcess = new Process(['az', 'acr', 'login', '--name', $registryName]);
        $loginProcess->setTimeout(120);

        $loginProcess->run(function ($type, $buffer) {
            if ($type === Process::OUT) {
                $this->output->write($buffer);
            }
        });

        if ($loginProcess->isSuccessful()) {
            $this->output->writeln('  <fg=green>✓</> Successfully logged in to ACR ('.$registryName.'.azurecr.io)');
            $this->output->writeln('');

            return true;
        } else {
            $this->output->writeln('');
            $this->components->error('ACR login failed: '.$loginProcess->getErrorOutput());
            $this->output->writeln('  Make sure you are logged in to Azure CLI (az login)');
            $this->output->writeln('');

            return false;
        }
    }
}

