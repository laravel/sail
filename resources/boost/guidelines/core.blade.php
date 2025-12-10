{{-- ReyemTech Sail – Laravel Boost guideline --}}

# ReyemTech Sail

- Provides Docker/Helm scaffolding for Laravel apps (web, worker, scheduler).
- Ships Helm stubs (deployments, ingress, secrets, scheduler CronJob, vendor PVC).
- `sail:build` command builds images and Helm chart; supports non-interactive flags.

## Install / Update
- Install package per project composer requirements.
- Run `php artisan boost:install` (or `boost:update`) so Boost pulls this guideline.

## Build Command (sail:build)
- Non-interactive flags:
  - `--environments=local,production`
  - `--architectures=linux/amd64,linux/arm64`
  - `--repository=ghcr.io|registry.gitlab.com|docker.io|none`
  - `--organization=acme`
  - `--domains=app.example.com,api.example.com`
  - `--build-version=1.2.3`
  - `--push` (push built images)
  - `--use-previous` (reuse last saved config, no prompts)
  - `--bump=patch|minor|major|no` (increments version; `no` keeps current)
- If any flag is provided, prompts are skipped. `repository=none` disables push.
- Writes `.env` keys: `SAIL_BUILD_*`, `SAIL_DEPLOY_DOMAINS`, `VITE_DEV_SERVER_URL`.

## Helm Notes
- Helm stubs live in `stubs/helm`.
- Scheduler CronJob mounts a vendor PVC when `scheduler.vendorPvc.enabled` (default true).
  - PVC name: `<name>-scheduler-vendor`
  - Default size: `5Gi`, storage class `sata`; override via `values.yaml`.
- Images derive from `global.tag` / `sail.build.version`; repositories use `<project>-web` / `<project>-worker`.

## Common Tasks
- Build with stored config and bump patch: `php artisan sail:build --use-previous --bump=patch`.
- Fully specified build: `php artisan sail:build --environments=production --architectures=linux/amd64,linux/arm64 --repository=ghcr.io --organization=acme --domains=app.example.com --build-version=1.2.3 --push --bump=no`.

## AI Hints
- Prefer non-interactive flags in automation (CI/CD).
- Keep vendor PVC enabled for scheduler to avoid repeated `composer install`.
- Update Helm values for storage class/size when cluster defaults differ.
