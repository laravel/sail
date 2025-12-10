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

Validation:
- Environments must be in `local, production`
- Architectures must be in the allowed list from the package
- Repository must be one of the known registries or `none`

## Helm chart notes

- Stubs live in `stubs/helm`
- Scheduler vendor PVC: enabled by default (`scheduler.vendorPvc.*`), default size 5Gi, storage class `sata`
- Resources & security defaults:
  - `resources` requests/limits set in `values.stub`
  - `securityContext` defaults to non-root, fsGroup 1000
- Probes: web gets readiness/liveness on `/up`

## Docker runtime

- PHP 8.x bake files reside in `runtimes/8.x`
- PHP 8.5 reuses the 8.x bake structure (`runtimes/8.5/docker-bake.hcl` targets 8.x, PHP_VERSION=8.5)
- Multi-stage targets: base, app, production (cli/fpm)

## Rollback (Helm)

```bash
helm rollback <release> <revision>
helm history <release>
```

## Contributing / Security

- Issues: https://github.com/reyemtech/sail/issues
- Security: https://github.com/laravel/sail/security/policy
- License: MIT
