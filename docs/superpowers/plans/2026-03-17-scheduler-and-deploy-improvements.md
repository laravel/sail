# Scheduler & Deploy Improvements Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the fragile vendor PVC from the scheduler, bake vendor into production images, add a presync migration Job, and streamline production container startup.

**Architecture:** Currently, production images strip `vendor/` at build time (via `REMOVE_VENDOR_NODE_MODULES`), then every pod and CronJob re-runs `composer install` at startup using a PVC or s6 service. This caused a 2-day scheduler outage from a stuck Cinder volume. The fix: keep `vendor/` in the image (only strip `node_modules/`), remove the PVC, run migrations as a presync Job instead of per-pod, and simplify the scheduler CronJob command.

**Tech Stack:** Docker (BuildKit/Bake), Helm, Kubernetes, s6-overlay, PHP/Composer, Laravel Artisan

---

## File Structure

### Files to Modify
- `runtimes/8.x/Dockerfile.app-build` — only remove `node_modules`, keep `vendor`; use `--no-dev` for production
- `runtimes/8.x/docker-bake.hcl` — rename variable `REMOVE_VENDOR_NODE_MODULES` → `REMOVE_NODE_MODULES`
- `runtimes/8.x/s6/app/laravel-prepare/up` — remove `composer install`, remove `migrate`, add post-autoload-dump
- `stubs/helm/templates/schedule-worker.stub` — remove `composer install` from command, remove vendor PVC mount
- `stubs/helm/templates/presync-image-check.stub` — add `imageCheck.enabled` gate + ArgoCD sync-wave annotation
- `stubs/helm/templates/pvc-scheduler-vendor.stub` — change default from `true` to `false`
- `stubs/helm/values.stub` — default `vendorPvc.enabled: false`, add `migrations` and `imageCheck` config sections
- `config/sail.php` — rename config key, add backward-compatible env var fallback
- `src/Console/Concerns/InteractsWithDocker.php` — rename variable references
- `src/Console/BuildCommand.php` — rename both `--remove-vendor-node-modules` and `--keep-vendor-node-modules` options

### Files to Create
- `stubs/helm/templates/presync-migrate.stub` — presync Job for `php artisan migrate --force`

### Files to Keep (no changes)
- `runtimes/8.x/s6/local/laravel-prepare/up` — local dev still needs `composer install` (volume-mounted app)
- `runtimes/8.x/s6/local/schedule/run` — already uses `schedule:work`, no changes needed

---

## Chunk 1: Docker Build Changes

### Task 1: Rename build variable and keep vendor in image

**Files:**
- Modify: `runtimes/8.x/Dockerfile.app-build`
- Modify: `runtimes/8.x/docker-bake.hcl`

- [ ] **Step 1: Update `Dockerfile.app-build` — rename ARG, only remove `node_modules`, use `--no-dev`**

The key changes:
1. Rename `REMOVE_VENDOR_NODE_MODULES` to `REMOVE_NODE_MODULES`
2. First cleanup block (line 13-14): only remove `node_modules` (not `vendor`)
3. Final cleanup block (lines 38-41): only remove `node_modules`
4. Use `--no-dev` on `composer install` — previously s6 would re-run with `--no-dev` in production, but since we're keeping vendor in the image it must be production-ready at build time
5. Remove `--no-scripts` — post-install scripts (like `post-autoload-dump`) need to run since s6 will no longer re-run `composer install`. Laravel's post-install scripts don't require a DB connection.

```dockerfile
FROM base

ARG REMOVE_NODE_MODULES=true

# Clean application directory
RUN rm -rf /var/www/*

COPY --from=app --chown=sail:sail / /var/www/
COPY --from=package preload/opcache.preload.php /var/www/bootstrap/opcache.preload.php

# Recreate empty storage structure
RUN find /var/www/storage ! -name '*.json' -mindepth 1 -delete \
    && if [ "$REMOVE_NODE_MODULES" = "true" ]; then \
        rm -rf /var/www/node_modules; \
    fi \
    && rm -rf /var/www/.env* /var/www/public/hot \
    && mkdir -p /var/www/storage/app \
    /var/www/bootstrap/cache \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs

# Set proper permissions
RUN chown -R sail:sail /var/www
RUN chmod -R 755 /var/www/

# Install PHP dependencies (production only, with scripts)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install and build frontend assets using npm
RUN npm ci

RUN npm run build

# Clean up build artifacts to reduce final image size
RUN if [ "$REMOVE_NODE_MODULES" = "true" ]; then \
        rm -rf /var/www/node_modules; \
    fi

RUN rm -rf /var/www/storage/framework/cache/*
RUN rm -rf /var/www/storage/framework/sessions/*
RUN rm -rf /var/www/storage/framework/views/*
RUN rm -rf /var/www/storage/logs/*

RUN rm -rf /var/www/bootstrap/cache/*
```

**Note on image size:** Keeping vendor adds ~100-150MB to the image. This is an acceptable tradeoff vs. the operational complexity of the PVC and per-pod `composer install`. Using `--no-dev` minimizes this by excluding dev dependencies.

- [ ] **Step 2: Update `docker-bake.hcl` — rename variable**

Change the variable name from `REMOVE_VENDOR_NODE_MODULES` to `REMOVE_NODE_MODULES` in three places:
1. Variable declaration (line 37-39)
2. `app-build` target args (line 78)

```hcl
variable "REMOVE_NODE_MODULES" {
    default = "true"
}
```

And in the `app-build` target:
```hcl
    args = {
        REMOVE_NODE_MODULES = "${REMOVE_NODE_MODULES}"
    }
```

- [ ] **Step 3: Commit**

```bash
git add runtimes/8.x/Dockerfile.app-build runtimes/8.x/docker-bake.hcl
git commit -m "feat: keep vendor in production images, only strip node_modules

Vendor was being removed from the production image and re-installed
at container startup via composer install. This required a PVC for
the scheduler CronJob which caused a 2-day outage when the Cinder
volume got stuck in attaching state.

Now vendor stays in the image. node_modules is still stripped since
frontend assets are already compiled.

BREAKING CHANGE: REMOVE_VENDOR_NODE_MODULES renamed to REMOVE_NODE_MODULES"
```

---

### Task 2: Rename PHP config and build command references

**Files:**
- Modify: `config/sail.php`
- Modify: `src/Console/Concerns/InteractsWithDocker.php`
- Modify: `src/Console/BuildCommand.php`

- [ ] **Step 1: Update `config/sail.php`**

Rename the config key from `remove_vendor_node_modules` to `remove_node_modules`. Add backward-compatible fallback for the old env var to ease migration:

```php
'remove_node_modules' => env('SAIL_BUILD_REMOVE_NODE_MODULES',
    env('SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES', true)),
```

- [ ] **Step 2: Update `InteractsWithDocker.php`**

Rename all references. The property:
```php
protected ?bool $removeNodeModules = null;
```

In `buildDockerImages()` (around line 152):
```php
$removeNodeModules = $this->removeNodeModules ?? config('sail.build.remove_node_modules', true);
```

In the args array (around line 162):
```php
'REMOVE_NODE_MODULES' => $removeNodeModules ? 'true' : 'false',
```

In `displayPreviousConfig()` (around line 281):
```php
$this->output->writeln('<fg=yellow>==></> <fg=green>Remove node_modules:</> '.($config['remove_node_modules'] ?? true ? '<bg=green;fg-black> true </>' : '<bg=red;fg=black> false </>'));
```

In `loadPreviousConfig()` (around line 295-296):
```php
if (isset($config['remove_node_modules'])) {
    $this->removeNodeModules = (bool) $config['remove_node_modules'];
}
```

In `saveConfig()` (around line 383-412):
```php
$removeNodeModules = $this->removeNodeModules ?? config('sail.build.remove_node_modules', true);
// ...
$config['remove_node_modules'] = $removeNodeModules;
// ...
$writer->set('SAIL_BUILD_REMOVE_NODE_MODULES', $config['remove_node_modules'] ? 'true' : 'false');
// ...
Config::set('sail.build.remove_node_modules', $config['remove_node_modules']);
```

- [ ] **Step 3: Update `BuildCommand.php`**

Rename BOTH CLI options and all references:

In `$signature` (lines 32-33), rename both options:
```php
{--remove-node-modules : Remove node_modules/ from final image (default: true)}
{--keep-node-modules : Keep node_modules/ in final image}';
```

Around line 88-90:
```php
if (! isset($this->removeNodeModules) && isset($config['remove_node_modules'])) {
    $this->removeNodeModules = (bool) $config['remove_node_modules'];
}
```

In `configFromOptions()` (around line 164-165), rename both option reads:
```php
$removeNodeModulesOption = $this->option('remove-node-modules');
$keepNodeModulesOption = $this->option('keep-node-modules');
```

Update the override check (around line 174-175):
```php
|| $removeNodeModulesOption === true
|| $keepNodeModulesOption === true;
```

Around lines 213-218:
```php
if ($keepNodeModulesOption === true) {
    $this->removeNodeModules = false;
} elseif ($removeNodeModulesOption === true) {
    $this->removeNodeModules = true;
} else {
    $this->removeNodeModules = $config['remove_node_modules'] ?? true;
}
```

Around line 234:
```php
'remove_node_modules' => $this->removeNodeModules,
```

- [ ] **Step 4: Update existing tests**

Check `tests/Feature/BuildCommandTest.php` for any references to `remove_vendor_node_modules` or `removeVendorNodeModules` and update them to the new names.

- [ ] **Step 5: Run tests**

```bash
cd ~/code/ReyemTech/sail && vendor/bin/phpunit
```

- [ ] **Step 6: Commit**

```bash
git add config/sail.php src/Console/Concerns/InteractsWithDocker.php src/Console/BuildCommand.php tests/
git commit -m "refactor: rename remove_vendor_node_modules to remove_node_modules

Config key, env var, CLI option, and internal references all updated.
Vendor is now kept in the image by default, so the option only
controls node_modules removal.

BREAKING CHANGE: SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES env var
renamed to SAIL_BUILD_REMOVE_NODE_MODULES. --remove-vendor-node-modules
CLI flag renamed to --remove-node-modules."
```

---

## Chunk 2: Production s6 Startup Simplification

### Task 3: Remove composer install and migrate from production s6

**Files:**
- Modify: `runtimes/8.x/s6/app/laravel-prepare/up`

- [ ] **Step 1: Simplify production laravel-prepare**

Since vendor is now in the image and migrations will run via a presync Job, the production startup only needs `optimize` and permission fixing.

Note: The Dockerfile now runs `composer install --no-dev` with scripts, so `post-autoload-dump` already ran at build time. No need to re-run composer scripts at startup.

```execlineb
#!/usr/bin/execlineb
with-contenv
cd /var/www

foreground { php artisan optimize }

foreground { chown -R sail:sail /var/www/storage/logs }

foreground { echo ready >&3 }
```

This removes:
- `composer install` (vendor is in the image with scripts already executed)
- `php artisan migrate --force` (will run as presync Job)
- `APP_ENV` conditional logic (no longer needed — build always uses `--no-dev`)

- [ ] **Step 2: Commit**

```bash
git add runtimes/8.x/s6/app/laravel-prepare/up
git commit -m "feat: remove composer install and migrate from production s6 startup

Vendor is now baked into the image so composer install is unnecessary.
Migrations move to a Kubernetes presync Job so they run once per
deploy instead of once per pod."
```

---

## Chunk 3: Helm Template Changes

### Task 4: Simplify scheduler CronJob

**Files:**
- Modify: `stubs/helm/templates/schedule-worker.stub`

- [ ] **Step 1: Update scheduler CronJob template**

Remove the `composer install` from the command and remove the vendor PVC volume mount entirely. The PVC template still exists for users who opt-in via `vendorPvc.enabled: true`, but the scheduler no longer references it:

```yaml
{{- if .Values.scheduler.enabled -}}
apiVersion: batch/v1
kind: CronJob
metadata:
  name: {{ include "sail.name" . }}-scheduler
  labels:
    {{- include "sail.labels" . | nindent 4 }}
    tier: scheduler
spec:
  schedule: "{{ .Values.scheduler.schedule | default "* * * * *" }}"
  successfulJobsHistoryLimit: {{ .Values.scheduler.successfulJobsHistoryLimit | default 5 }}
  failedJobsHistoryLimit: {{ .Values.scheduler.failedJobsHistoryLimit | default 5 }}
  jobTemplate:
    spec:
      template:
        spec:
          {{- if or .Values.worker.imagePullSecret .Values.global.imagePullSecret }}
          imagePullSecrets:
            - name: {{ .Values.worker.imagePullSecret | default .Values.global.imagePullSecret }}
          {{- end }}
          containers:
            - name: scheduler
              image: "{{ .Values.worker.image.repository | lower }}:{{ .Values.worker.image.tag | default .Values.global.tag | default "latest" | lower }}"
              imagePullPolicy: {{ .Values.worker.image.pullPolicy | default .Values.global.pullPolicy | default "IfNotPresent" }}
              command: ["php", "artisan", "schedule:run"]
              envFrom:
                - secretRef:
                    name: {{ include "sail.name" . }}-environment
                {{- if .Values.typesense.enabled }}
                - secretRef:
                    name: {{ .Values.typesense.secret | default "typesense" | lower }}
                {{- end }}
          restartPolicy: OnFailure
{{- end -}}
```

- [ ] **Step 2: Commit**

```bash
git add stubs/helm/templates/schedule-worker.stub
git commit -m "feat: simplify scheduler CronJob, remove composer install and PVC mount

Vendor is now in the image so the scheduler just runs schedule:run
directly. No PVC needed, eliminating the class of volume attachment
failures that caused multi-day scheduler outages."
```

---

### Task 5: Create presync migration Job template

**Files:**
- Create: `stubs/helm/templates/presync-migrate.stub`

- [ ] **Step 1: Create the migration Job template**

This follows the same pattern as the existing `presync-image-check.stub` — both ArgoCD and Helm hooks, runs before pods roll out:

```yaml
{{- if .Values.migrations.enabled | default true -}}
apiVersion: batch/v1
kind: Job
metadata:
  name: {{ include "sail.name" . }}-presync-migrate
  labels:
    {{- include "sail.labels" . | nindent 4 }}
  annotations:
    argocd.argoproj.io/hook: PreSync
    argocd.argoproj.io/hook-delete-policy: BeforeHookCreation
    argocd.argoproj.io/sync-wave: "-5"
    helm.sh/hook: pre-upgrade,pre-install
    helm.sh/hook-weight: "-5"
    helm.sh/hook-delete-policy: before-hook-creation
spec:
  backoffLimit: 3
  activeDeadlineSeconds: {{ .Values.migrations.activeDeadlineSeconds | default 300 }}
  template:
    metadata:
      labels:
        {{- include "sail.selectorLabels" . | nindent 8 }}
        tier: presync
    spec:
      restartPolicy: Never
      {{- if or .Values.worker.imagePullSecret .Values.global.imagePullSecret }}
      imagePullSecrets:
        - name: {{ .Values.worker.imagePullSecret | default .Values.global.imagePullSecret }}
      {{- end }}
      containers:
        - name: migrate
          image: "{{ .Values.worker.image.repository | lower }}:{{ .Values.worker.image.tag | default .Values.global.tag | default "latest" | lower }}"
          imagePullPolicy: {{ .Values.worker.image.pullPolicy | default .Values.global.pullPolicy | default "IfNotPresent" }}
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - secretRef:
                name: {{ include "sail.name" . }}-environment
            {{- if .Values.typesense.enabled }}
            - secretRef:
                name: {{ .Values.typesense.secret | default "typesense" | lower }}
            {{- end }}
{{- end -}}
```

Key design decisions:
- `argocd.argoproj.io/sync-wave: "-5"` ensures ArgoCD runs this after image check (`-10`). Helm uses `hook-weight` for the same ordering — both annotations are needed since ArgoCD ignores `hook-weight`.
- Uses the worker image (has PHP CLI + vendor)
- `backoffLimit: 3` retries on failure
- `activeDeadlineSeconds` defaults to 300 but is configurable via `migrations.activeDeadlineSeconds` for large databases
- `BeforeHookCreation` delete policy cleans up old Job before creating new one
- If migration fails, the entire deploy is blocked (desired behavior)

- [ ] **Step 2: Commit**

```bash
git add stubs/helm/templates/presync-migrate.stub
git commit -m "feat: add presync migration Job template

Runs php artisan migrate --force once per deploy as a PreSync hook.
Works with both ArgoCD and Helm. Runs after the image check
(weight -5 vs -10) but before pods roll out. If migration fails,
the deploy is blocked."
```

---

### Task 6: Update values.stub with new defaults

**Files:**
- Modify: `stubs/helm/values.stub`

- [ ] **Step 1: Update scheduler defaults and add new config sections**

In the scheduler section, change `vendorPvc.enabled` default to `false`:

```yaml
# Laravel scheduler configuration
scheduler:
  enabled: true
  schedule: "*/5 * * * *"
  successfulJobsHistoryLimit: 5
  failedJobsHistoryLimit: 5
  vendorPvc:
    enabled: false
    accessModes:
      - ReadWriteOnce
    size: 5Gi
    storageClassName: "sata"
```

Add new sections after the scheduler block:

```yaml
# Pre-deploy image verification
imageCheck:
  enabled: true

# Pre-deploy database migrations
migrations:
  enabled: true
  activeDeadlineSeconds: 300
```

- [ ] **Step 2: Update PVC template default**

Also update `stubs/helm/templates/pvc-scheduler-vendor.stub` line 1 to change the default from `true` to `false`, matching the new values default:

Change:
```yaml
{{- if and (.Values.scheduler.enabled) (.Values.scheduler.vendorPvc.enabled | default true) -}}
```

To:
```yaml
{{- if and (.Values.scheduler.enabled) (.Values.scheduler.vendorPvc.enabled | default false) -}}
```

This ensures the PVC is not created even if a user has an older `values.yaml` that lacks the `vendorPvc` key entirely.

- [ ] **Step 3: Commit**

```bash
git add stubs/helm/values.stub stubs/helm/templates/pvc-scheduler-vendor.stub
git commit -m "feat: default vendorPvc disabled, add migrations and imageCheck config

vendorPvc defaults to disabled since vendor is now in the image.
migrations.enabled and imageCheck.enabled provide toggles for the
presync Jobs. PVC template default also changed to false."
```

---

### Task 7: Gate image check template on config and add sync-wave

**Files:**
- Modify: `stubs/helm/templates/presync-image-check.stub`

- [ ] **Step 1: Add enabled check and ArgoCD sync-wave annotation**

Two changes:
1. Wrap template in `imageCheck.enabled` gate
2. Add `argocd.argoproj.io/sync-wave: "-10"` annotation so ArgoCD orders it before the migration Job (`-5`)

Change the opening from:
```yaml
{{- $tag := .Values.global.tag | default "latest" }}
```

To:
```yaml
{{- if .Values.imageCheck.enabled | default true -}}
{{- $tag := .Values.global.tag | default "latest" }}
```

Add the sync-wave annotation after `argocd.argoproj.io/hook-delete-policy`:
```yaml
    argocd.argoproj.io/sync-wave: "-10"
```

And change the closing from:
```yaml
              echo "All images verified successfully."
{{- end }}
```

To:
```yaml
              echo "All images verified successfully."
{{- end }}
{{- end -}}
```

- [ ] **Step 2: Commit**

```bash
git add stubs/helm/templates/presync-image-check.stub
git commit -m "feat: gate image check presync Job on imageCheck.enabled config

Allows disabling the presync image verification via values.yaml.
Defaults to enabled for backwards compatibility."
```

---

## Chunk 4: Update sail:build command to publish new templates

### Task 8: Ensure new templates are published by the build command

**Files:**
- Check: `src/Console/BuildCommand.php` or wherever `sail:build` publishes Helm stubs

- [ ] **Step 1: Verify the stub publishing logic**

The `sail:build` command should already publish all files from `stubs/helm/templates/` to the project's `helm/templates/`. Check the publishing logic in the build command to confirm the new `presync-migrate.stub` will be picked up automatically.

Search for how stubs are published:
```bash
cd ~/code/ReyemTech/sail && grep -rn "stub\|publish\|helm" src/Console/ --include="*.php" | grep -i "template\|stub\|copy\|publish"
```

If the publishing uses a glob/directory copy, no changes needed. If it explicitly lists files, add `presync-migrate.stub` to the list.

- [ ] **Step 2: Run tests to verify nothing is broken**

```bash
cd ~/code/ReyemTech/sail && vendor/bin/phpunit
```

- [ ] **Step 3: Commit (if changes were needed)**

```bash
git add src/
git commit -m "feat: include presync-migrate template in stub publishing"
```

---

## Chunk 5: CI/CD Pipeline for Sail Package

### Task 9: Enable and update the test workflow

**Files:**
- Rename: `.github/workflows/tests.yml.disabled` → `.github/workflows/tests.yml`

- [ ] **Step 1: Rename and update the test workflow**

Rename `.github/workflows/tests.yml.disabled` to `.github/workflows/tests.yml`. The existing content is good — it creates a fresh Laravel app, links the local sail repo, installs it, starts containers, runs migrations, and runs tests across PHP 8.2-8.5. Keep it as-is, it already tests the package properly.

```bash
cd ~/code/ReyemTech/sail
mv .github/workflows/tests.yml.disabled .github/workflows/tests.yml
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "ci: enable test workflow

Runs on push to master and PRs. Tests across PHP 8.2-8.5 with
Laravel 12 by creating a fresh app, linking sail, and running
the full test suite inside containers."
```

---

### Task 10: Enable static analysis and shellcheck workflows

**Files:**
- Rename: `.github/workflows/static-analysis.yml.disabled` → `.github/workflows/static-analysis.yml`
- Rename: `.github/workflows/shellcheck.yml.disabled` → `.github/workflows/shellcheck.yml`

- [ ] **Step 1: Enable both workflows**

```bash
cd ~/code/ReyemTech/sail
mv .github/workflows/static-analysis.yml.disabled .github/workflows/static-analysis.yml
mv .github/workflows/shellcheck.yml.disabled .github/workflows/shellcheck.yml
```

The static analysis workflow uses `laravel/.github` reusable workflow which runs PHPStan. The shellcheck workflow runs differential ShellCheck on PRs. Both are good as-is.

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/static-analysis.yml .github/workflows/shellcheck.yml
git commit -m "ci: enable static analysis and shellcheck workflows"
```

---

### Task 11: Create release workflow with release-please and Packagist

**Files:**
- Create: `.github/workflows/release.yml`
- Create: `release-please-config.json`
- Create: `.release-please-manifest.json`

- [ ] **Step 1: Create release-please config**

`release-please-config.json`:
```json
{
  "$schema": "https://raw.githubusercontent.com/googleapis/release-please/main/schemas/config.json",
  "release-type": "php",
  "packages": {
    ".": {
      "changelog-path": "CHANGELOG.md",
      "bump-minor-pre-major": true,
      "bump-patch-for-minor-pre-major": true
    }
  }
}
```

`.release-please-manifest.json` — set to `2.0.0` since this release introduces breaking changes (renamed env vars, CLI options, vendor now in image):
```json
{
  ".": "2.0.0"
}
```

Note: The first release-please PR will create `v2.0.1` or the next version based on conventional commits. Tag `v2.0.0` manually before merging the first release-please PR:
```bash
cd ~/code/ReyemTech/sail && git tag v2.0.0 && git push origin v2.0.0
```

- [ ] **Step 2: Create the release workflow**

`.github/workflows/release.yml`:
```yaml
name: Release

on:
  push:
    branches: [master]

permissions:
  contents: write
  pull-requests: write

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: true
      matrix:
        include:
          - php: '8.2'
            laravel: 12
          - php: '8.4'
            laravel: 12
    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }}
    steps:
      - uses: actions/checkout@v4
        with:
          path: 'sail'

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, fileinfo
          tools: composer:v2
          coverage: none

      - name: Create a new Laravel application
        run: composer create-project laravel/laravel app "${{ matrix.laravel }}.x" --remove-vcs --no-interaction --prefer-dist

      - name: Link Sail repository
        run: |
          composer config minimum-stability dev
          composer config repositories.sail path ../sail
          composer require reyemtech/sail:* --dev -W
        working-directory: app

      - name: Install Sail
        run: |
          php artisan sail:install --php=${{ matrix.php }} --no-interaction
          php artisan sail:publish --no-interaction
        working-directory: app

      - name: Run PHPUnit tests
        run: |
          cd ../sail && vendor/bin/phpunit
        working-directory: app

  release-please:
    needs: test
    runs-on: ubuntu-latest
    outputs:
      release_created: ${{ steps.release.outputs.release_created }}
      tag_name: ${{ steps.release.outputs.tag_name }}
      version: ${{ steps.release.outputs.version }}
    steps:
      - uses: googleapis/release-please-action@v4
        id: release
        with:
          config-file: release-please-config.json
          manifest-file: .release-please-manifest.json

      - name: Summary
        if: steps.release.outputs.release_created
        run: |
          echo "## Release Created" >> "$GITHUB_STEP_SUMMARY"
          echo "" >> "$GITHUB_STEP_SUMMARY"
          echo "- **Version:** ${{ steps.release.outputs.version }}" >> "$GITHUB_STEP_SUMMARY"
          echo "- **Tag:** ${{ steps.release.outputs.tag_name }}" >> "$GITHUB_STEP_SUMMARY"
          echo "- Packagist will auto-update via GitHub webhook" >> "$GITHUB_STEP_SUMMARY"
```

Note: Packagist auto-updates when it detects a new tag via GitHub webhook. No explicit Packagist API call needed — just ensure the webhook is configured on the GitHub repo (Settings → Webhooks → Packagist URL: `https://packagist.org/api/github?username=PACKAGIST_USERNAME`).

- [ ] **Step 3: Delete the disabled workflows that are now replaced**

```bash
cd ~/code/ReyemTech/sail
rm .github/workflows/issues.yml.disabled
rm .github/workflows/pull-requests.yml.disabled
rm .github/workflows/update-changelog.yml.disabled
```

The `update-changelog.yml` is replaced by release-please (which manages the changelog). The `issues.yml` and `pull-requests.yml` were upstream Laravel workflows not needed for the fork.

- [ ] **Step 4: Verify the Packagist webhook is configured**

Check `https://github.com/reyemtech/sail/settings/hooks` for the Packagist webhook. If not configured:
1. Go to GitHub repo Settings → Webhooks → Add webhook
2. Payload URL: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
3. Content type: `application/json`
4. Secret: your Packagist API token
5. Events: Just the push event

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/release.yml release-please-config.json .release-please-manifest.json
git rm .github/workflows/issues.yml.disabled .github/workflows/pull-requests.yml.disabled .github/workflows/update-changelog.yml.disabled
git commit -m "ci: add release-please workflow for automated versioning and Packagist publishing

Pipeline: push to master → tests (PHP 8.2+8.4) → release-please
creates version PR → merge PR → tag created → Packagist auto-updates
via webhook.

Removes unused upstream Laravel workflow stubs."
```

---

## Chunk 6: Update the reyemtech/laravel project

### Task 12: Apply changes to the laravel project's Helm chart

After the sail package changes are released, update the laravel project:

**Files:**
- Modify: `helm/values.yaml` in `~/code/ReyemTech/laravel`
- Modify: `helm/templates/schedule-worker.yaml` in `~/code/ReyemTech/laravel`
- Create: `helm/templates/presync-migrate.yaml` in `~/code/ReyemTech/laravel`
- Delete: `helm/templates/pvc-scheduler-vendor.yaml` in `~/code/ReyemTech/laravel` (or set enabled: false)

- [ ] **Step 1: Update composer constraint for sail v2**

In `~/code/ReyemTech/laravel/composer.json`, change:
```json
"reyemtech/sail": "^2.0"
```

Then run:
```bash
composer update reyemtech/sail
```

- [ ] **Step 2: Update `helm/values.yaml`**

Add the new config sections and update scheduler:
```yaml
migrations:
  enabled: true

imageCheck:
  enabled: true

scheduler:
  enabled: true
  schedule: '*/5 * * * *'
  successfulJobsHistoryLimit: 5
  failedJobsHistoryLimit: 5
  vendorPvc:
    enabled: false
```

- [ ] **Step 3: Update `SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES` env var**

In `.env` (and any CI/deployment configs), rename:
```
SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES → SAIL_BUILD_REMOVE_NODE_MODULES
```

The backward-compatible fallback will handle the transition, but update for cleanliness.

- [ ] **Step 4: Regenerate Helm templates from updated sail package**

Run `sail:build` or manually copy the updated stubs to the project's `helm/templates/` directory.

- [ ] **Step 3: Verify Helm template renders correctly**

```bash
helm template reyemtech ./helm --debug
```

Check that:
- Scheduler CronJob uses `php artisan schedule:run` (no `composer install`)
- Migration presync Job is rendered with correct hooks
- Image check presync Job is rendered
- No PVC is rendered (vendorPvc.enabled: false)

- [ ] **Step 4: Deploy and verify**

Deploy via ArgoCD and confirm:
1. Image check presync Job runs first
2. Migration presync Job runs second
3. Web/worker pods start without running `composer install` or `migrate`
4. Scheduler CronJob runs successfully with just `php artisan schedule:run`

- [ ] **Step 5: Commit**

```bash
git add helm/
git commit -m "feat: adopt new sail deploy strategy - vendor in image, presync migrations

- Scheduler no longer needs composer install or vendor PVC
- Migrations run once per deploy via presync Job
- Image check gated on imageCheck.enabled config"
```
