<p align="center"><img width="294" height="69" src="/art/logo.svg" alt="Logo Laravel Sail"></p>

<p align="center">
<a href="https://packagist.org/packages/laravel/sail"><img src="https://img.shields.io/packagist/dt/laravel/sail" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/sail"><img src="https://img.shields.io/packagist/v/laravel/sail" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/sail"><img src="https://img.shields.io/packagist/l/laravel/sail" alt="License"></a>
</p>

## Introduction

This fork of Laravel Sail adds:
- Helm chart generation (with scheduler vendor PVC support)
- Non-interactive `sail:build` flags (`--use-previous`, `--bump`, etc.)
- Laravel Boost guideline for IDE/AI context
- Multi-stage Docker builds (bake) shared across PHP versions (8.x/8.5)

## Quick start

```bash
composer require reyemtech/sail --dev
php artisan sail:install --php=8.4   # or 8.5
php artisan sail:publish
```

## Multi-project support

This fork includes a `sail-wrapper` that automatically finds and runs the nearest project's Sail script. This allows you to use `sail` from any directory when working with multiple Sail projects on the same machine.

**Automatic installation**: The wrapper is automatically installed to `~/.local/bin/sail` (or `~/bin` if available) when you install or update the package via Composer.

**Usage**: Simply run `sail` from any directory, and it will automatically find and execute the nearest project's `vendor/bin/sail`:

```bash
cd /path/to/project-a
sail up -d        # Uses project-a's Sail

cd /path/to/project-b
sail artisan migrate  # Uses project-b's Sail
```

**Manual installation**: If automatic installation fails, you can manually install:

```bash
cp vendor/reyemtech/sail/bin/sail-wrapper ~/.local/bin/sail
chmod +x ~/.local/bin/sail
```

**Note**: Make sure `~/.local/bin` (or `~/bin`) is in your PATH. Add to your `~/.bashrc` or `~/.zshrc`:

```bash
export PATH="$HOME/.local/bin:$PATH"
```

## Build with bake + Helm

```bash
php artisan sail:build \
  --environments=production \
  --architectures=linux/amd64,linux/arm64 \
  --repository=ghcr.io \
  --organization=acme \
  --domains=app.example.com \
  --build-version=1.2.3 \
  --push \
  --use-previous \
  --bump=patch
```

Key flags:
- `--use-previous`: reuse the last saved config without prompts
- `--bump=patch|minor|major|no`: bump version non-interactively
- `--repository=none`: local-only build (disables push)
- `--remove-vendor-node-modules`: Remove vendor/ and node_modules/ from final image (default: true)
- `--keep-vendor-node-modules`: Keep vendor/ and node_modules/ in final image

Validation:
- Environments must be in `local, production`
- Architectures must be in the allowed list from the package
- Repository must be one of the known registries, `none`, or a full registry URL (e.g., `888657980245.dkr.ecr.us-east-1.amazonaws.com`)

### Registry Support

The build command supports multiple container registries with automatic authentication:

**Standard Registries:**
- GitHub Container Registry (`ghcr.io`)
- Docker Hub (`docker.io`)
- GitLab Container Registry (`registry.gitlab.com`)
- Quay.io (`quay.io`)
- Custom registries (any valid registry URL)

**AWS ECR:**
```bash
php artisan sail:build --repository=888657980245.dkr.ecr.us-east-1.amazonaws.com --push
# Or use shorthand:
php artisan sail:build --repository=ecr --push
# Requires: AWS CLI configured (aws configure)
# Environment variables: AWS_REGION, AWS_ACCOUNT_ID (optional)
```

**Azure ACR:**
```bash
php artisan sail:build --repository=myregistry.azurecr.io --push
# Or use shorthand:
php artisan sail:build --repository=azurecr --push
# Requires: Azure CLI installed and logged in (az login)
# Environment variable: AZURE_ACR_NAME (optional)
```

The build command will automatically check if you're logged in and prompt for authentication if needed.

## Helm Commands

### Regenerate Helm Chart

Regenerate the Helm chart without building Docker images:

```bash
# Regenerate with current version
php artisan sail:helm

# Regenerate with specific version
php artisan sail:helm --chart-version=1.2.3

# Bump version and regenerate
php artisan sail:helm --bump=patch

# Skip version update in Chart.yaml
php artisan sail:helm --no-version-update
```

This command:
- Updates Helm templates from stubs
- Merges new configuration variables from `values.stub` into existing `values.yaml`
- Updates `Chart.yaml` version (unless `--no-version-update` is used)
- Validates the chart using `helm lint`

Useful when you need to update templates or add new configuration options without rebuilding Docker images.

## Helm Chart Notes

- Stubs live in `stubs/helm`
- Scheduler vendor PVC: enabled by default (`scheduler.vendorPvc.*`), default size 5Gi, storage class `sata`
- Resources & security defaults:
  - `resources` requests/limits set in `values.stub`
  - `securityContext` defaults to non-root, fsGroup 1000
- Probes: web gets readiness/liveness on `/up`
- **Autoscaling**: HPA enabled by default for web tier, configurable per tier
- **Pod Disruption Budgets**: Configurable PDBs for high availability
- **ServiceAccounts**: Optional ServiceAccount creation with annotations
- **External Secrets**: Automatic API version detection (v1 or v1beta1)

## Docker runtime

- PHP 8.x bake files reside in `runtimes/8.x`
- PHP 8.5 reuses the 8.x bake structure (`runtimes/8.5/docker-bake.hcl` targets 8.x, PHP_VERSION=8.5)
- Multi-stage targets: base, app, production (cli/fpm)

## CI/CD Integration

Generate CI/CD configuration files for automated Docker builds:

```bash
# Interactive selection
php artisan sail:ci

# Direct selection
php artisan sail:ci --provider=github-actions
php artisan sail:ci --provider=gitlab-ci
php artisan sail:ci --provider=azure-devops
php artisan sail:ci --provider=circleci
php artisan sail:ci --provider=aws-codebuild
php artisan sail:ci --provider=travis

# Overwrite existing configuration
php artisan sail:ci --provider=github-actions --overwrite
```

### Supported CI Platforms

1. **GitHub Actions** - `.github/workflows/build.yml`
2. **GitLab CI/CD** - `.gitlab-ci.yml`
3. **Azure DevOps Pipelines** - `azure-pipelines/build.yml`
4. **CircleCI** - `.circleci/config.yml`
5. **AWS CodeBuild** - `buildspec.yml`
6. **Travis CI** - `.travis.yml`

All CI configurations:
- Build on push to `main`/`master` branches
- Build on version tags (`v*`)
- Support multi-architecture builds (amd64, arm64)
- Include registry authentication (ECR, ACR, standard)
- Generate both Docker images and Helm charts
- Extract version from git tags or generate date-based versions

### CI Setup Requirements

**GitHub Actions:**
- Secrets: `REGISTRY_USERNAME`, `REGISTRY_PASSWORD` (or `GHCR_IO_USERNAME`, `GHCR_IO_PASSWORD`)
- For ECR: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`
- For ACR: `AZURE_CREDENTIALS`

**GitLab CI:**
- CI/CD Variables: `REGISTRY_USERNAME`, `REGISTRY_PASSWORD` (or `CI_REGISTRY_USER`, `CI_REGISTRY_PASSWORD`)
- For ECR: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`
- For ACR: `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID`

**Azure DevOps:**
- Variables: `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`
- Service connections for ACR and AWS

**CircleCI:**
- Environment Variables: `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`
- For ECR: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`

**AWS CodeBuild:**
- Environment Variables: `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`
- IAM role for ECR authentication (no credentials needed)

**Travis CI:**
- Environment Variables: `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`

## Rollback (Helm)

```bash
helm rollback <release> <revision>
helm history <release>
```

## Contributing / Security

- Issues: https://github.com/reyemtech/sail/issues
- Security: https://github.com/laravel/sail/security/policy
- License: MIT
