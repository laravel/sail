# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ReyemTech Sail is a fork of Laravel Sail extended with Docker multi-architecture builds, Helm chart generation/validation, multi-registry support, and CI/CD pipeline generation. It conflicts with `laravel/sail` and replaces it as a drop-in.

## Commands

```bash
# Testing (uses Orchestra Testbench)
composer test                    # All tests
composer test:feature            # Feature tests only
composer test:integration        # Integration tests only
vendor/bin/phpunit --filter=TestName  # Single test by name

# Static analysis
vendor/bin/phpstan analyse src   # PHPStan level 0
```

## Architecture

**Namespace:** `Laravel\Sail\` (PSR-4 from `src/`)

**Service Provider:** `SailServiceProvider` implements `DeferrableProvider`. Registers 7 artisan commands and pushes `ForceHttps` middleware in production.

**Console Commands** (`src/Console/`):
- `sail:install` — Initial project setup (docker-compose, .env, phpunit)
- `sail:add` — Add services to existing installation
- `sail:publish` — Publish Docker runtimes, bin scripts, database configs
- `sail:build` — Build Docker images with multi-arch support + generate Helm charts
- `sail:helm` — Regenerate Helm charts only (merges values.stub)
- `sail:helm:validate` — Validate Helm charts via `helm lint`
- `sail:ci` — Generate CI/CD configs (GitHub Actions, GitLab CI, Azure DevOps, CircleCI, AWS CodeBuild, Travis CI)

**Traits** (`src/Console/Concerns/`) — Commands compose behavior via traits:
- `InteractsWithDocker` — Build config, Dockerfile/bake generation, version bumping
- `InteractsWithDockerRegistry` — Multi-registry auth (ECR, ACR, GHCR, GitLab, Docker Hub, Quay, Harbor)
- `InteractsWithDockerComposeServices` — Service stubs and docker-compose management
- `InteractsWithDockerPrompts` — Interactive prompts with non-interactive CLI flag overrides
- `InteractsWithHelm` — Helm chart generation, values merging, validation

**Configuration** (`config/sail.php`): Build and deploy settings via `SAIL_BUILD_*` and `SAIL_DEPLOY_*` env vars. Config is persisted to `.env` using `mirazmac/dotenvwriter`.

**Templates** (`stubs/`): `.stub` files for docker-compose services, Helm charts (deployments, HPA, PDB, ingress, external secrets, ArgoCD presync), and CI/CD pipelines.

**Docker Runtimes** (`runtimes/`): Multi-stage builds using `docker-bake.hcl` with targets: base, app, production (cli/fpm). PHP 8.x and 8.5 supported.

## Code Style

- Laravel preset (StyleCI)
- 4-space indentation, LF line endings
- YAML files use 2-space indentation
