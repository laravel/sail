# Sail Resource Hygiene Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the disconnected s6-log pipeline (nginx/php-fpm output to stdout), retire the `logger` workaround, make the logging destination values-configurable (`stdout|file|both`, default `both`), and ship Helm chart hygiene defaults for `LOG_CHANNEL`, resources, and cache/session drivers so the temporary `apps` namespace LimitRange override can be removed.

**Architecture:** The sail image already has a correct s6-log producer/consumer pipeline with stdout tee, but nginx and php-fpm bypass it by writing directly to files on disk (298Mi/pod of unbounded `nginx.access.log`). The fix is to (1) change the producers' config to write to stdout/stderr so the existing pipeline gets input, (2) template the s6-log args from env vars so the destination (file / stdout / both) is values-configurable, (3) delete the `logger` workaround service, (4) add a chart-owned `<name>-defaults` Secret prepended to `envFrom` so overrideable defaults like `LOG_CHANNEL` stay overrideable by the consumer's environment secret (envFrom precedence: later wins), and (5) add resource defaults and conditional cache/session drivers.

**Tech Stack:** Docker (BuildKit/Bake), Alpine 3.21, s6-overlay v3.2.0.3, nginx 1.26, php-fpm 8.4, Helm 3, Kubernetes 1.27+, PHPUnit 10 (existing test harness invokes `docker` and `helm` binaries via `Symfony\Component\Process\Process`).

**Spec:** `docs/superpowers/specs/2026-04-16-sail-resource-hygiene-design.md`

---

## File Structure

### Files to Modify

**Image (runtimes/8.x/):**
- `nginx.conf` — redirect `access_log` to `/dev/stdout`, `error_log` to `/dev/stderr`
- `php-fpm.conf` — redirect `error_log` + `access.log` to `/proc/self/fd/2`
- `Dockerfile.base` — remove `ln -s .../logger/ .../user/contents.d/logger` line
- `s6/app/nginx-log-prepare/up` — template s6-log args from env
- `s6/app/nginx-log/run` — becomes stub; overwritten at container start by `-prepare`
- `s6/app/php-fpm-log-prepare/up` — convert execlineb to shell, template args
- `s6/app/php-fpm-log/run` — becomes stub; overwritten at container start

**Chart (stubs/helm/):**
- `templates/_helpers.tpl` — add `sail.resources` helper
- `templates/_spec.stub` — inject SAIL_LOG_* env vars, prepend defaults-secret envFrom, use resources helper
- `templates/deployment-web.stub` + `deployment-worker.stub` — merge top-level `resources`, `logging` into `$main`
- `templates/schedule-worker.stub` — add defaults-secret envFrom
- `values.stub` — document new `logging:` and `app:` blocks

### Files to Create

- `runtimes/8.x/s6/app/nginx-log/dependencies.d/nginx-log-prepare` — empty dep marker file
- `stubs/helm/templates/defaults-secret.stub` — chart-owned Secret for overrideable env defaults
- `tests/Feature/HelmTemplateTest.php` — render assertions
- `tests/Integration/S6LogPipelineTest.php` — container-boot integration test

### Files to Delete

- `runtimes/8.x/s6/app/logger/run`
- `runtimes/8.x/s6/app/logger/type`
- `runtimes/8.x/s6/app/logger/dependencies.d/php-fpm`
- `runtimes/8.x/s6/app/logger/` (whole tree)

---

## Chunk 1: Chart — Test Harness and Defaults Secret

### Task 1: Add Helm template test harness

**Files:**
- Create: `tests/Feature/HelmTemplateTest.php`

This test class runs `helm template` against `stubs/helm/` and asserts on YAML output. It is regex-based, uses `Symfony\Component\Process\Process`, matches the pattern of `BuildCommandTest.php`, and runs fast (no docker).

- [ ] **Step 1: Confirm `helm` CLI is available**

Run: `helm version --short`
Expected: a version string like `v3.X.X+...`. If missing, install via `brew install helm` (macOS) or the project's standard tooling path.

- [ ] **Step 2: Create the test file**

Create `tests/Feature/HelmTemplateTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;
use Symfony\Component\Process\Process;

class HelmTemplateTest extends TestCase
{
    protected string $chartPath;
    protected string $tmpValuesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $helmCheck = new Process(['helm', 'version', '--short']);
        $helmCheck->run();
        if (! $helmCheck->isSuccessful()) {
            $this->markTestSkipped('helm CLI not available on PATH; skipping helm template tests.');
        }

        $this->chartPath = realpath(__DIR__.'/../../stubs/helm');
        $this->tmpValuesDir = sys_get_temp_dir().'/sail-helm-test-'.uniqid();
        File::makeDirectory($this->tmpValuesDir.'/templates', 0755, true);
        File::copy($this->chartPath.'/Chart.stub', $this->tmpValuesDir.'/Chart.yaml');
        File::copy($this->chartPath.'/values.stub', $this->tmpValuesDir.'/values.yaml');
        foreach (File::files($this->chartPath.'/templates') as $tpl) {
            $dest = $this->tmpValuesDir.'/templates/'.preg_replace('/\.stub$/', '.yaml', $tpl->getFilename());
            File::copy($tpl->getPathname(), $dest);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tmpValuesDir)) {
            File::deleteDirectory($this->tmpValuesDir);
        }
        parent::tearDown();
    }

    protected function renderChart(array $valuesOverrides = []): string
    {
        $valuesFile = $this->tmpValuesDir.'/overrides.yaml';
        File::put($valuesFile, \Symfony\Component\Yaml\Yaml::dump(array_merge([
            'name' => 'testapp',
            'secret' => ['enabled' => false, 'path' => '/fake', 'store' => 'fake'],
        ], $valuesOverrides), 10, 2));

        $cmd = ['helm', 'template', 'test-release', $this->tmpValuesDir, '-f', $valuesFile];
        $p = new Process($cmd);
        $p->run();

        if (! $p->isSuccessful()) {
            $this->fail("helm template failed:\n".$p->getErrorOutput());
        }

        return $p->getOutput();
    }

    public function test_chart_renders_without_error(): void
    {
        $out = $this->renderChart();
        $this->assertStringContainsString('kind: Deployment', $out);
        $this->assertStringContainsString('name: testapp-web', $out);
    }
}
```

- [ ] **Step 3: Run the test, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php -v`
Expected: 1 test, 1 assertion, PASS. If `values.stub` placeholders like `<project-name>` cause render failure, the `renderChart()` helper already overrides `name`, `secret.path`, `secret.store` with real values. Add more overrides as needed.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/HelmTemplateTest.php
git commit -m "test: add helm template test harness

Renders the chart via helm template against a temp directory and
returns stdout for regex assertions. Used by subsequent resource
hygiene tasks. Skips gracefully if helm CLI is not installed."
```

---

### Task 2: Create the chart-owned defaults Secret

**Files:**
- Modify: `tests/Feature/HelmTemplateTest.php`
- Create: `stubs/helm/templates/defaults-secret.stub`
- Modify: `stubs/helm/values.stub`

- [ ] **Step 1: Add failing test**

Append to `HelmTemplateTest.php`:

```php
public function test_defaults_secret_is_rendered(): void
{
    $out = $this->renderChart();
    $this->assertMatchesRegularExpression(
        '/kind:\s*Secret\n[^-]+name:\s*testapp-defaults/',
        $out,
        'Expected a Secret named testapp-defaults to be rendered'
    );
}

public function test_log_channel_defaults_to_stderr(): void
{
    $out = $this->renderChart();
    $this->assertMatchesRegularExpression(
        '/name:\s*testapp-defaults.*?LOG_CHANNEL:\s*"?stderr"?/s',
        $out
    );
}

public function test_log_channel_can_be_overridden(): void
{
    $out = $this->renderChart(['app' => ['logChannel' => 'daily']]);
    $this->assertMatchesRegularExpression(
        '/name:\s*testapp-defaults.*?LOG_CHANNEL:\s*"?daily"?/s',
        $out
    );
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter "defaults_secret|log_channel" -v`
Expected: FAIL — Secret not rendered, `.Values.app` is nil.

- [ ] **Step 3: Create `stubs/helm/templates/defaults-secret.stub`**

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: {{ include "sail.name" . }}-defaults
  labels:
    {{- include "sail.labels" . | nindent 4 }}
type: Opaque
stringData:
  LOG_CHANNEL: {{ .Values.app.logChannel | default "stderr" | quote }}
  {{- if .Values.redis.secret }}
  CACHE_DRIVER: {{ .Values.app.cacheDriver | default "redis" | quote }}
  SESSION_DRIVER: {{ .Values.app.sessionDriver | default "redis" | quote }}
  {{- end }}
```

- [ ] **Step 4: Add `app:` section to `stubs/helm/values.stub`**

Append after the `redis`, `s3` blocks (around line 293), before `# Typesense search`:

```yaml
# Laravel application behavior defaults. These become a chart-owned
# Secret (<name>-defaults) prepended to envFrom; the consumer's
# <name>-environment secret takes precedence (envFrom later-entry-wins).
app:
  # LOG_CHANNEL default - stderr flows logs through php-fpm stderr to
  # the s6-log pipeline (stdout + rotated file per sail.logging.mode).
  logChannel: stderr
  # CACHE_DRIVER / SESSION_DRIVER are only written to the defaults
  # secret when redis.secret is set. Unset (file default) otherwise.
  cacheDriver: redis
  sessionDriver: redis
```

- [ ] **Step 5: Run tests, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter "defaults_secret|log_channel" -v`
Expected: 3 passing tests.

- [ ] **Step 6: Commit**

```bash
git add stubs/helm/templates/defaults-secret.stub stubs/helm/values.stub tests/Feature/HelmTemplateTest.php
git commit -m "feat(helm): add chart-owned defaults Secret

Provides overrideable defaults (LOG_CHANNEL, CACHE/SESSION_DRIVER
when redis is plumbed) via a Secret prepended to envFrom in a later
task. Consumer's environment secret wins (envFrom later-entry-wins)."
```

---

### Task 3: Cover conditional CACHE/SESSION_DRIVER behavior

**Files:**
- Modify: `tests/Feature/HelmTemplateTest.php`

- [ ] **Step 1: Add tests with YAML parsing helper**

```php
public function test_cache_session_absent_without_redis(): void
{
    $out = $this->renderChart();
    $data = $this->extractSecretStringData($out, 'testapp-defaults');
    $this->assertArrayNotHasKey('CACHE_DRIVER', $data);
    $this->assertArrayNotHasKey('SESSION_DRIVER', $data);
}

public function test_cache_session_present_with_redis(): void
{
    $out = $this->renderChart(['redis' => ['secret' => 'my-redis']]);
    $data = $this->extractSecretStringData($out, 'testapp-defaults');
    $this->assertSame('redis', $data['CACHE_DRIVER'] ?? null);
    $this->assertSame('redis', $data['SESSION_DRIVER'] ?? null);
}

public function test_cache_driver_override(): void
{
    $out = $this->renderChart([
        'redis' => ['secret' => 'my-redis'],
        'app' => ['cacheDriver' => 'database', 'sessionDriver' => 'cookie'],
    ]);
    $data = $this->extractSecretStringData($out, 'testapp-defaults');
    $this->assertSame('database', $data['CACHE_DRIVER']);
    $this->assertSame('cookie', $data['SESSION_DRIVER']);
}

protected function extractSecretStringData(string $yaml, string $name): array
{
    $docs = preg_split('/^---$/m', $yaml);
    foreach ($docs as $doc) {
        if (preg_match('/kind:\s*Secret\b/', $doc)
            && preg_match('/name:\s*'.preg_quote($name, '/').'\b/', $doc)) {
            $parsed = \Symfony\Component\Yaml\Yaml::parse($doc) ?? [];
            return $parsed['stringData'] ?? [];
        }
    }
    return [];
}
```

- [ ] **Step 2: Run, expected PASS (conditional already implemented in Task 2)**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter cache -v`
Expected: 3 passing tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/HelmTemplateTest.php
git commit -m "test(helm): cover CACHE/SESSION_DRIVER conditional and override"
```

---

### Task 4: Prepend defaults-secret to envFrom in `_spec.stub`

**Files:**
- Modify: `tests/Feature/HelmTemplateTest.php`
- Modify: `stubs/helm/templates/_spec.stub`
- Modify: `stubs/helm/templates/schedule-worker.stub`

- [ ] **Step 1: Add failing test**

```php
public function test_envfrom_order_defaults_then_environment(): void
{
    $out = $this->renderChart();
    $this->assertMatchesRegularExpression(
        '/envFrom:\s*'
            .'\n\s*-\s*secretRef:\s*'
            .'\n\s*name:\s*testapp-defaults\s*'
            .'\n\s*-\s*secretRef:\s*'
            .'\n\s*name:\s*testapp-environment/',
        $out,
        'Expected envFrom order: testapp-defaults then testapp-environment'
    );
}
```

- [ ] **Step 2: Run test, verify fail**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter envfrom_order -v`
Expected: FAIL — current chart only has the `-environment` secretRef.

- [ ] **Step 3: Edit `stubs/helm/templates/_spec.stub`**

Find the existing `envFrom:` block (around line 266):

```yaml
          envFrom:
            - secretRef:
                name: {{ include "sail.name" . }}-environment
            {{- if .main.typesense.enabled }}
            - secretRef:
                name: {{ .main.typesense.secret | default (printf "%s-typesense" (include "sail.name" .)) }}
            {{- end }}
```

Change to prepend the defaults-secret:

```yaml
          envFrom:
            - secretRef:
                name: {{ include "sail.name" . }}-defaults
            - secretRef:
                name: {{ include "sail.name" . }}-environment
            {{- if .main.typesense.enabled }}
            - secretRef:
                name: {{ .main.typesense.secret | default (printf "%s-typesense" (include "sail.name" .)) }}
            {{- end }}
```

- [ ] **Step 4: Edit `stubs/helm/templates/schedule-worker.stub`**

Find the `envFrom:` block (around line 26) and apply the same prepend:

```yaml
              envFrom:
                - secretRef:
                    name: {{ include "sail.name" . }}-defaults
                - secretRef:
                    name: {{ include "sail.name" . }}-environment
                {{- if .Values.typesense.enabled }}
                - secretRef:
                    name: {{ .Values.typesense.secret | default (printf "%s-typesense" (include "sail.name" .)) }}
                {{- end }}
```

- [ ] **Step 5: Run test, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter envfrom_order -v`
Expected: PASS.

Full suite:
Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php -v`
Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add stubs/helm/templates/_spec.stub stubs/helm/templates/schedule-worker.stub tests/Feature/HelmTemplateTest.php
git commit -m "feat(helm): prepend defaults secret to envFrom

Makes chart defaults available while still allowing the consumer's
<name>-environment secret to override (envFrom later-entry-wins)."
```

---

## Chunk 2: Chart — Logging env vars and resource defaults

### Task 5: Inject SAIL_LOG_* env vars and pipe logging values through context

**Files:**
- Modify: `tests/Feature/HelmTemplateTest.php`
- Modify: `stubs/helm/templates/deployment-web.stub`
- Modify: `stubs/helm/templates/deployment-worker.stub`
- Modify: `stubs/helm/templates/_spec.stub`
- Modify: `stubs/helm/values.stub`

- [ ] **Step 1: Add failing tests**

```php
public function test_sail_log_env_vars_defaults(): void
{
    $out = $this->renderChart();
    $this->assertMatchesRegularExpression('/name:\s*SAIL_LOG_MODE\s*\n\s*value:\s*"both"/', $out);
    $this->assertMatchesRegularExpression('/name:\s*SAIL_LOG_MAX_ARCHIVES\s*\n\s*value:\s*"20"/', $out);
    $this->assertMatchesRegularExpression('/name:\s*SAIL_LOG_ROTATE_SIZE\s*\n\s*value:\s*"10000000"/', $out);
}

public function test_sail_log_env_vars_override(): void
{
    $out = $this->renderChart([
        'logging' => ['mode' => 'stdout', 'maxArchives' => 5, 'maxFileSize' => 20000000],
    ]);
    $this->assertMatchesRegularExpression('/name:\s*SAIL_LOG_MODE\s*\n\s*value:\s*"stdout"/', $out);
    $this->assertMatchesRegularExpression('/name:\s*SAIL_LOG_MAX_ARCHIVES\s*\n\s*value:\s*"5"/', $out);
    $this->assertMatchesRegularExpression('/name:\s*SAIL_LOG_ROTATE_SIZE\s*\n\s*value:\s*"20000000"/', $out);
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter sail_log_env -v`
Expected: FAIL.

- [ ] **Step 3: Edit `stubs/helm/templates/deployment-web.stub` and `deployment-worker.stub`**

In both files, add `"logging"` and `"resources"` to the `$main` dict (the resources key is needed for Task 6). After the change in `deployment-web.stub`:

```yaml
{{- $tier := "web" }}
{{- $scoped := get .Values $tier | default dict }}
{{- $main := dict
    "global" (.Values.global | default dict)
    "secret" (.Values.secret | default dict)
    "name" (.Values.name | default "website")
    "typesense" (.Values.typesense | default dict)
    "database" (.Values.database | default dict)
    "redis" (.Values.redis | default dict)
    "s3" (.Values.s3 | default dict)
    "serviceAccountName" (include "sail.serviceAccountName" .)
    "annotations" (.Values.annotations | default dict)
    "podAnnotations" (.Values.podAnnotations | default dict)
    "affinity" (.Values.affinity | default dict)
    "tolerations" (.Values.tolerations | default list)
    "nodeSelector" (.Values.nodeSelector | default dict)
    "logging" (.Values.logging | default dict)
    "resources" (.Values.resources | default dict)
}}
{{- $ctx := merge (dict "tier" $tier "main" $main "Chart" .Chart "Release" .Release "Values" .Values "Capabilities" .Capabilities) $scoped }}
{{- include "sail.laravelSpec" $ctx }}
```

Apply the same two-line addition (`"logging"` and `"resources"`) to `deployment-worker.stub`.

- [ ] **Step 4: Inject SAIL_LOG_* env vars in `_spec.stub`**

The existing `env:` key is guarded by `{{- if $hasInfraSecrets }}`. Since SAIL_LOG_* must always appear, rewrite so `env:` is unconditional and the infra blocks become inner conditionals.

Two precise edits:

**Edit A (opens the env block):** Find these three lines in `_spec.stub`:

```yaml
          {{- $hasInfraSecrets := or .main.database.secret .main.redis.secret .main.s3.secret }}
          {{- if $hasInfraSecrets }}
          env:
```

Replace with:

```yaml
          env:
            - name: SAIL_LOG_MODE
              value: {{ .main.logging.mode | default "both" | quote }}
            - name: SAIL_LOG_MAX_ARCHIVES
              value: {{ .main.logging.maxArchives | default 20 | quote }}
            - name: SAIL_LOG_ROTATE_SIZE
              value: {{ .main.logging.maxFileSize | default 10000000 | quote }}
```

(Removes both the `$hasInfraSecrets` variable definition and the `{{- if $hasInfraSecrets }}` guard; keeps the `env:` key and adds three always-on entries.)

**Edit B (removes the matching close):** Find the `{{- end }}` that sits immediately before `envFrom:` (this is the `{{- end }}` that used to close `{{- if $hasInfraSecrets }}`). Delete only that one line. The inner `{{- if .main.database.secret }}` / `.redis.secret` / `.s3.secret` blocks and their own `{{- end }}` lines stay untouched.

Context (before):

```yaml
            {{- end }}
            {{- end }}    <-- this line is the one to delete (matches $hasInfraSecrets if)
          envFrom:
```

Context (after):

```yaml
            {{- end }}
          envFrom:
```

Verify with `helm template` (the test in Step 1 of this task does this).

- [ ] **Step 5: Add `logging:` section to `values.stub`**

Append after the `app:` section from Task 2:

```yaml
# Logging pipeline behavior (web tier nginx/php-fpm logs).
# Worker/scheduler pods bypass s6 entirely; their logs always go to stdout.
logging:
  # stdout - logs only to container stdout, kubelet rotates (~12Mi/pod).
  # file   - logs only to /var/log/{nginx,php-fpm}/ rotated by s6-log.
  # both   - logs to rotated files AND stdout tee (default).
  mode: both
  # s6-log -n argument: number of rotated archives retained per service.
  maxArchives: 20
  # s6-log -s argument: bytes per file before rotation. Raw integer (no suffix).
  maxFileSize: 10000000
```

- [ ] **Step 6: Run tests, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter sail_log_env -v`
Expected: 2 passing tests.

Full suite:
Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php -v`
Expected: all tests PASS (envFrom order, defaults secret, LOG_CHANNEL, CACHE/SESSION still green).

- [ ] **Step 7: Commit**

```bash
git add stubs/helm/templates/_spec.stub stubs/helm/templates/deployment-web.stub stubs/helm/templates/deployment-worker.stub stubs/helm/values.stub tests/Feature/HelmTemplateTest.php
git commit -m "feat(helm): inject SAIL_LOG_* env vars into web/worker

sail.logging.mode (stdout|file|both, default both), maxArchives, and
maxFileSize flow through as env vars consumed by the image's
nginx-log-prepare / php-fpm-log-prepare oneshots at container start."
```

---

### Task 6: Add `sail.resources` helper with tier/global/default fallback

**Files:**
- Modify: `tests/Feature/HelmTemplateTest.php`
- Modify: `stubs/helm/templates/_helpers.tpl`
- Modify: `stubs/helm/templates/_spec.stub`

- [ ] **Step 1: Add failing tests**

```php
public function test_default_resources_when_nothing_set(): void
{
    $out = $this->renderChart(['resources' => null]);
    $this->assertMatchesRegularExpression(
        '/resources:\s*\n\s*requests:\s*\n\s*cpu:\s*100m\s*\n\s*memory:\s*256Mi/',
        $out
    );
}

public function test_top_level_resources_used_when_tier_unset(): void
{
    $out = $this->renderChart([
        'resources' => [
            'requests' => ['cpu' => '250m', 'memory' => '512Mi'],
            'limits' => ['cpu' => '750m', 'memory' => '1Gi'],
        ],
    ]);
    $this->assertMatchesRegularExpression('/resources:\s*\n\s*requests:\s*\n\s*cpu:\s*250m/', $out);
}

public function test_tier_resources_win_over_top_level(): void
{
    $out = $this->renderChart([
        'resources' => [
            'requests' => ['cpu' => '250m', 'memory' => '512Mi'],
        ],
        'web' => [
            'resources' => [
                'requests' => ['cpu' => '500m', 'memory' => '768Mi'],
            ],
        ],
    ]);
    $this->assertMatchesRegularExpression(
        '/name:\s*testapp-web.*?resources:\s*\n\s*requests:\s*\n\s*cpu:\s*500m/s',
        $out
    );
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter resources -v`
Expected: FAIL.

- [ ] **Step 3: Add `sail.resources` helper to `_helpers.tpl`**

Append:

```go
{{/*
Render the container resources block.
Fallback order:
  1. tier-scoped .resources (from $scoped in deployment-*.stub)
  2. top-level .main.resources
  3. hardcoded defaults (cpu 100m/1000m, memory 256Mi/1Gi)

Ephemeral-storage is deliberately omitted — spec 1's LimitRange
injects 500Mi/2Gi at the namespace level.

Input:
  .tier   — tier-specific resources (may be nil/empty)
  .global — top-level resources (may be nil/empty)
*/}}
{{- define "sail.resources" -}}
{{- $tier := .tier | default dict -}}
{{- $global := .global | default dict -}}
{{- if $tier }}
{{- toYaml $tier -}}
{{- else if $global }}
{{- toYaml $global -}}
{{- else }}
requests:
  cpu: 100m
  memory: 256Mi
limits:
  cpu: 1000m
  memory: 1Gi
{{- end }}
{{- end -}}
```

- [ ] **Step 4: Use the helper in `_spec.stub`**

Find the existing block (around lines 57–60):

```yaml
          {{- with .resources }}
          resources:
            {{- toYaml . | nindent 12 }}
          {{- end }}
```

Replace with:

```yaml
          resources:
            {{- include "sail.resources" (dict "tier" .resources "global" .main.resources) | nindent 12 }}
```

- [ ] **Step 5: Run tests, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php --filter resources -v`
Expected: 3 passing tests.

Full suite sanity:
Run: `vendor/bin/phpunit tests/Feature/HelmTemplateTest.php -v`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add stubs/helm/templates/_helpers.tpl stubs/helm/templates/_spec.stub tests/Feature/HelmTemplateTest.php
git commit -m "feat(helm): default resources with tier+global+hardcoded fallback

sail.resources helper picks tier-scoped resources first, falls back
to top-level .Values.resources, falls back to hardcoded (100m cpu /
256Mi memory requests, 1000m / 1Gi limits). Ephemeral storage is NOT
in the default - ceded to spec 1's namespace LimitRange."
```

---

## Chunk 3: Image — nginx and php-fpm config fixes

### Task 7: Scaffold the container integration test

**Files:**
- Create: `tests/Integration/S6LogPipelineTest.php`

This test builds the `sail-base` image and runs containers with various `SAIL_LOG_MODE` values, inspecting file system state and running processes via `docker exec`. Slow (image build ~minutes) but gated on Docker availability like `FullBuildTest`.

- [ ] **Step 1: Verify the base image can be built standalone**

The existing `docker-bake.hcl` may orchestrate the multi-stage build. First try `docker buildx bake` to build just the base stage:

Run:
```bash
cd runtimes/8.x
docker buildx bake base --load --set base.tags=sail-s6-log-test:local 2>&1 | tail -30
```

If `bake` does not have a `base` target, fall back to direct docker build:

```bash
cd runtimes/8.x
docker buildx build --load --target runtime -t sail-s6-log-test:local -f Dockerfile.base .
```

Note the working command — the test scaffolding in Step 2 uses whichever form succeeds.

- [ ] **Step 2: Create the test file**

Create `tests/Integration/S6LogPipelineTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Integration;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;
use Symfony\Component\Process\Process;

class S6LogPipelineTest extends TestCase
{
    protected static string $imageTag = 'sail-s6-log-test:local';
    protected static bool $imageBuilt = false;
    protected array $runningContainers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dockerCheck = new Process(['docker', 'buildx', 'version']);
        $dockerCheck->run();
        if (! $dockerCheck->isSuccessful()) {
            $this->markTestSkipped('Docker buildx unavailable; skipping image integration test.');
        }

        if (! self::$imageBuilt) {
            $this->buildBaseImage();
            self::$imageBuilt = true;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->runningContainers as $cid) {
            (new Process(['docker', 'rm', '-f', $cid]))->run();
        }
        $this->runningContainers = [];
        parent::tearDown();
    }

    protected function buildBaseImage(): void
    {
        $runtimePath = realpath(__DIR__.'/../../runtimes/8.x');
        // Adjust this command to match what worked in Task 7 Step 1.
        $p = new Process([
            'docker', 'buildx', 'build',
            '--load',
            '-t', self::$imageTag,
            '-f', $runtimePath.'/Dockerfile.base',
            $runtimePath,
        ], null, null, null, 900);
        $p->mustRun();
    }

    protected function runContainer(array $env = []): string
    {
        $args = ['docker', 'run', '-d', '--rm'];
        foreach ($env as $k => $v) {
            $args[] = '-e';
            $args[] = "$k=$v";
        }
        $args[] = self::$imageTag;
        $p = new Process($args);
        $p->mustRun();
        $cid = trim($p->getOutput());
        $this->runningContainers[] = $cid;

        $this->waitForProcess($cid, 's6-svscan', 15);
        return $cid;
    }

    protected function waitForProcess(string $cid, string $procName, int $timeoutSec): void
    {
        $deadline = time() + $timeoutSec;
        while (time() < $deadline) {
            $p = new Process(['docker', 'exec', $cid, 'pgrep', '-f', $procName]);
            $p->run();
            if ($p->isSuccessful() && trim($p->getOutput()) !== '') {
                return;
            }
            usleep(500_000);
        }
        $this->fail("process $procName did not start within {$timeoutSec}s in container $cid");
    }

    protected function execInContainer(string $cid, array $cmd): string
    {
        $p = new Process(array_merge(['docker', 'exec', $cid], $cmd));
        $p->run();
        return $p->getOutput();
    }

    public function test_base_image_builds_successfully(): void
    {
        $p = new Process(['docker', 'image', 'inspect', self::$imageTag]);
        $p->run();
        $this->assertTrue($p->isSuccessful());
    }
}
```

- [ ] **Step 3: Run the test, verify PASS**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter test_base_image_builds -v`
Expected: PASS (or SKIPPED if docker unavailable).

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/S6LogPipelineTest.php
git commit -m "test: add s6 log pipeline integration test scaffolding

Builds the base image once per class and provides helpers for
running short-lived containers with env overrides. Subsequent tasks
add assertions for the s6-log wiring fixes."
```

---

### Task 8: Fix nginx.conf to write to stdout/stderr

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`
- Modify: `runtimes/8.x/nginx.conf`

- [ ] **Step 1: Add failing tests**

Append to `S6LogPipelineTest.php`:

```php
public function test_nginx_conf_points_to_stdout_stderr(): void
{
    $cid = $this->runContainer();
    $conf = $this->execInContainer($cid, ['cat', '/etc/nginx/http.d/default.conf']);
    $this->assertMatchesRegularExpression('#access_log\s+/dev/stdout\b#', $conf);
    $this->assertMatchesRegularExpression('#error_log\s+/dev/stderr\b#', $conf);
    $this->assertStringNotContainsString('/var/log/nginx/nginx.access.log', $conf);
    $this->assertStringNotContainsString('/var/log/nginx/nginx.error.log', $conf);
}

public function test_nginx_does_not_create_old_log_files(): void
{
    $cid = $this->runContainer();
    sleep(2);
    $listing = $this->execInContainer($cid, ['ls', '-la', '/var/log/nginx/']);
    $this->assertStringNotContainsString('nginx.access.log', $listing);
    $this->assertStringNotContainsString('nginx.error.log', $listing);
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter nginx_ -v`
Expected: FAIL.

- [ ] **Step 3: Edit `runtimes/8.x/nginx.conf`**

Replace lines 5-6:

```nginx
    error_log  /var/log/nginx/nginx.error.log;
    access_log /var/log/nginx/nginx.access.log;
```

With:

```nginx
    error_log  /dev/stderr warn;
    access_log /dev/stdout main;
```

All other lines (including `access_log off;` location blocks) unchanged.

- [ ] **Step 4: Rebuild + run tests**

Run:
```bash
docker image rm sail-s6-log-test:local || true
vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter nginx_ -v
```

Expected: 2 passing tests.

- [ ] **Step 5: Commit**

```bash
git add runtimes/8.x/nginx.conf tests/Integration/S6LogPipelineTest.php
git commit -m "fix(image): nginx writes to stdout/stderr instead of files

Was writing to /var/log/nginx/nginx.access.log (298Mi/day observed in
production, unbounded). Now writes to /dev/stdout + /dev/stderr, which
feeds into the existing s6-log pipeline producer-consumer pipe."
```

---

### Task 9: Fix php-fpm.conf to write to /proc/self/fd/2

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`
- Modify: `runtimes/8.x/php-fpm.conf`

- [ ] **Step 1: Add failing tests**

```php
public function test_php_fpm_conf_points_to_fd2(): void
{
    $cid = $this->runContainer();
    $conf = $this->execInContainer($cid, ['cat', '/etc/php/php-fpm.d/docker.conf']);
    $this->assertMatchesRegularExpression('#error_log\s*=\s*/proc/self/fd/2#', $conf);
    $this->assertMatchesRegularExpression('#access\.log\s*=\s*/proc/self/fd/2#', $conf);
    $this->assertStringNotContainsString('/var/log/php/php-fpm.err.log', $conf);
    $this->assertStringNotContainsString('/var/log/php/php-fpm.out.log', $conf);
}

public function test_php_fpm_does_not_create_old_log_files(): void
{
    $cid = $this->runContainer();
    sleep(3);
    $listing = $this->execInContainer($cid, ['ls', '-la', '/var/log/php/']);
    $this->assertStringNotContainsString('php-fpm.err.log', $listing);
    $this->assertStringNotContainsString('php-fpm.out.log', $listing);
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter php_fpm_ -v`
Expected: FAIL.

- [ ] **Step 3: Edit `runtimes/8.x/php-fpm.conf`**

Current file:

```ini
[global]
error_log = /var/log/php/php-fpm.err.log

; https://github.com/docker-library/php/pull/725#issuecomment-443540114
log_limit = 81920

[www]
; php-fpm closes STDOUT on startup, so sending logs to /proc/self/fd/1 does not work.
; https://bugs.php.net/bug.php?id=73886
access.log = /var/log/php/php-fpm.out.log
user = sail
group = sail

pm = dynamic
...
```

Replace with:

```ini
[global]
; Containerized php-fpm: send error log to the container stderr (fd 2).
; php-fpm keeps fd 2 open; fd 1 is closed on startup
; (https://bugs.php.net/bug.php?id=73886), so /proc/self/fd/2 is the
; portable container destination for both error_log and access.log.
error_log = /proc/self/fd/2

; https://github.com/docker-library/php/pull/725#issuecomment-443540114
log_limit = 81920

[www]
access.log = /proc/self/fd/2
user = sail
group = sail

pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 10

clear_env = no

; Ensure worker stdout and stderr are sent to the main error log.
catch_workers_output = yes
decorate_workers_output = no
```

- [ ] **Step 4: Rebuild + run tests**

```bash
docker image rm sail-s6-log-test:local || true
vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter php_fpm_ -v
```

Expected: 2 passing tests.

- [ ] **Step 5: Commit**

```bash
git add runtimes/8.x/php-fpm.conf tests/Integration/S6LogPipelineTest.php
git commit -m "fix(image): php-fpm writes to fd/2 instead of files

Both error_log and access.log go to /proc/self/fd/2 (stderr). Feeds
the php-fpm-log s6-log consumer alongside nginx."
```

---

## Chunk 4: Image — retire the logger service

### Task 10: Delete the logger service tree and its symlink

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`
- Delete: `runtimes/8.x/s6/app/logger/` (whole tree)
- Modify: `runtimes/8.x/Dockerfile.base`

- [ ] **Step 1: Add failing tests**

```php
public function test_logger_tail_loop_is_absent(): void
{
    $cid = $this->runContainer();
    sleep(2);
    $pids = $this->execInContainer(
        $cid,
        ['pgrep', '-f', 'find /var/log /var/www/storage/logs -type f -name']
    );
    $this->assertEmpty(trim($pids), 'logger tail-loop process is still running');
}

public function test_user_bundle_does_not_reference_logger(): void
{
    $cid = $this->runContainer();
    $p = new Process([
        'docker', 'exec', $cid,
        'test', '-e', '/etc/s6-overlay/s6-rc.d/user/contents.d/logger',
    ]);
    $p->run();
    $this->assertFalse(
        $p->isSuccessful(),
        '/etc/s6-overlay/s6-rc.d/user/contents.d/logger still present'
    );
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter "logger_|user_bundle" -v`
Expected: both FAIL.

- [ ] **Step 3: Delete the logger service tree**

```bash
rm -rf runtimes/8.x/s6/app/logger
```

- [ ] **Step 4: Edit `runtimes/8.x/Dockerfile.base`**

Find (line 103):

```dockerfile
COPY --from=runtime ./s6/app /etc/s6-overlay/s6-rc.d/
RUN ln -s /etc/s6-overlay/s6-rc.d/logger/ /etc/s6-overlay/s6-rc.d/user/contents.d/logger
```

Delete the RUN line:

```dockerfile
COPY --from=runtime ./s6/app /etc/s6-overlay/s6-rc.d/
```

- [ ] **Step 5: Rebuild + run tests**

```bash
docker image rm sail-s6-log-test:local || true
vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter "logger_|user_bundle" -v
```

Expected: 2 passing tests.

- [ ] **Step 6: Commit**

```bash
git add -u runtimes/8.x/s6/app/logger runtimes/8.x/Dockerfile.base tests/Integration/S6LogPipelineTest.php
git commit -m "feat(image): retire the logger service

The busy-loop tail -F workaround is obsoleted by the producer-consumer
pipeline fix in earlier tasks. Logs now flow through s6-log's native
tee (file + stdout), not via a shell script scraping disk. Also removes
the dangling symlink in Dockerfile.base that referenced the deleted dir."
```

---

## Chunk 5: Image — template s6-log args from env

### Task 11: Add the nginx-log dependency on nginx-log-prepare

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`
- Create: `runtimes/8.x/s6/app/nginx-log/dependencies.d/nginx-log-prepare`

- [ ] **Step 1: Add failing test**

```php
public function test_nginx_log_depends_on_its_prepare(): void
{
    $cid = $this->runContainer();
    $p = new Process([
        'docker', 'exec', $cid,
        'test', '-f',
        '/etc/s6-overlay/s6-rc.d/nginx-log/dependencies.d/nginx-log-prepare',
    ]);
    $p->run();
    $this->assertTrue(
        $p->isSuccessful(),
        'nginx-log service is missing its dependency on nginx-log-prepare'
    );
}
```

- [ ] **Step 2: Run test, verify fail**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter nginx_log_depends -v`
Expected: FAIL.

- [ ] **Step 3: Create the empty dep file**

```bash
mkdir -p runtimes/8.x/s6/app/nginx-log/dependencies.d
touch runtimes/8.x/s6/app/nginx-log/dependencies.d/nginx-log-prepare
```

Matches the pattern `php-fpm-log/dependencies.d/php-fpm-log-prepare` already uses.

- [ ] **Step 4: Rebuild + run test**

```bash
docker image rm sail-s6-log-test:local || true
vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter nginx_log_depends -v
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add runtimes/8.x/s6/app/nginx-log/dependencies.d/nginx-log-prepare tests/Integration/S6LogPipelineTest.php
git commit -m "fix(image): add nginx-log to nginx-log-prepare dependency

Parity with php-fpm-log/dependencies.d/php-fpm-log-prepare. Required
for the next task - nginx-log-prepare will write the nginx-log run
script at container start, so nginx-log must not start first."
```

---

### Task 12: Template nginx-log/run via nginx-log-prepare

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`
- Modify: `runtimes/8.x/s6/app/nginx-log-prepare/up`
- Modify: `runtimes/8.x/s6/app/nginx-log/run`

- [ ] **Step 1: Add failing tests**

```php
public function test_nginx_log_both_mode_args(): void
{
    $cid = $this->runContainer();
    sleep(3);
    $run = $this->execInContainer($cid, ['cat', '/etc/s6-overlay/s6-rc.d/nginx-log/run']);
    $this->assertMatchesRegularExpression(
        '#s6-log\s+-b\s+n20\s+s10000000\s+T\s+!"gzip -nq9"\s+/var/log/nginx\s+p\[nginx\]\s+1#',
        $run
    );
}

public function test_nginx_log_stdout_mode_args(): void
{
    $cid = $this->runContainer(['SAIL_LOG_MODE' => 'stdout']);
    sleep(3);
    $run = $this->execInContainer($cid, ['cat', '/etc/s6-overlay/s6-rc.d/nginx-log/run']);
    $this->assertMatchesRegularExpression(
        '#s6-log\s+-b\s+n20\s+s10000000\s+T\s+!"gzip -nq9"\s+p\[nginx\]\s+1#',
        $run
    );
    $this->assertStringNotContainsString('/var/log/nginx', $run);
}

public function test_nginx_log_file_mode_args(): void
{
    $cid = $this->runContainer(['SAIL_LOG_MODE' => 'file']);
    sleep(3);
    $run = $this->execInContainer($cid, ['cat', '/etc/s6-overlay/s6-rc.d/nginx-log/run']);
    $this->assertMatchesRegularExpression(
        '#s6-log\s+-b\s+n20\s+s10000000\s+T\s+!"gzip -nq9"\s+/var/log/nginx\s+p\[nginx\]$#m',
        $run
    );
    $this->assertDoesNotMatchRegularExpression('/p\[nginx\]\s+1$/m', $run);
}

public function test_nginx_log_custom_rotation_args(): void
{
    $cid = $this->runContainer([
        'SAIL_LOG_MODE' => 'both',
        'SAIL_LOG_MAX_ARCHIVES' => '5',
        'SAIL_LOG_ROTATE_SIZE' => '5000000',
    ]);
    sleep(3);
    $run = $this->execInContainer($cid, ['cat', '/etc/s6-overlay/s6-rc.d/nginx-log/run']);
    $this->assertMatchesRegularExpression('#s6-log\s+-b\s+n5\s+s5000000\s+T#', $run);
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter nginx_log_ -v`
Expected: FAIL on mode variants.

- [ ] **Step 3: Rewrite `runtimes/8.x/s6/app/nginx-log-prepare/up`**

Replace file contents:

```sh
#!/bin/sh
set -e

# Ensure log directory exists regardless of mode.
mkdir -p /var/log/nginx
chown root:root /var/log/nginx
chmod 02755 /var/log/nginx

MODE="${SAIL_LOG_MODE:-both}"
ARCHIVES="${SAIL_LOG_MAX_ARCHIVES:-20}"
SIZE="${SAIL_LOG_ROTATE_SIZE:-10000000}"

case "$MODE" in
  stdout) DEST='p[nginx] 1' ;;
  file)   DEST='/var/log/nginx p[nginx]' ;;
  both)   DEST='/var/log/nginx p[nginx] 1' ;;
  *)
    echo "nginx-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

cat > /etc/s6-overlay/s6-rc.d/nginx-log/run <<RUN_SCRIPT
#!/bin/sh
exec s6-log -b n${ARCHIVES} s${SIZE} T !"gzip -nq9" ${DEST}
RUN_SCRIPT
chmod +x /etc/s6-overlay/s6-rc.d/nginx-log/run
```

- [ ] **Step 4: Replace `runtimes/8.x/s6/app/nginx-log/run` with a stub**

The existing static run file is overwritten at boot. Leave a valid fallback so s6-rc graph validation does not fail at image-build time:

```sh
#!/bin/sh
# Regenerated by nginx-log-prepare at container start based on SAIL_LOG_*
# env vars. This fallback body matches the default-both mode; the
# prepare oneshot runs first due to the dependencies.d marker added in
# the previous task.
exec s6-log -b n20 s10000000 T !"gzip -nq9" /var/log/nginx p[nginx] 1
```

- [ ] **Step 5: Rebuild + run tests**

```bash
docker image rm sail-s6-log-test:local || true
vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter nginx_log_ -v
```

Expected: 4 passing tests.

- [ ] **Step 6: Commit**

```bash
git add runtimes/8.x/s6/app/nginx-log-prepare/up runtimes/8.x/s6/app/nginx-log/run tests/Integration/S6LogPipelineTest.php
git commit -m "feat(image): template nginx-log run from SAIL_LOG_* env

SAIL_LOG_MODE (stdout|file|both, default both), SAIL_LOG_MAX_ARCHIVES
(default 20), SAIL_LOG_ROTATE_SIZE (default 10000000 bytes) now drive
the s6-log rotation + tee args at container start."
```

---

### Task 13: Template php-fpm-log/run via php-fpm-log-prepare (convert to shell)

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`
- Modify: `runtimes/8.x/s6/app/php-fpm-log-prepare/up`
- Modify: `runtimes/8.x/s6/app/php-fpm-log/run`

- [ ] **Step 1: Add failing tests**

```php
public function test_php_fpm_log_both_mode_args(): void
{
    $cid = $this->runContainer();
    sleep(3);
    $run = $this->execInContainer($cid, ['cat', '/etc/s6-overlay/s6-rc.d/php-fpm-log/run']);
    $this->assertMatchesRegularExpression(
        '#s6-log\s+-b\s+n20\s+s10000000\s+T\s+!"gzip -nq9"\s+/var/log/php-fpm\s+p\[php-fpm\]\s+1#',
        $run
    );
}

public function test_php_fpm_log_stdout_mode_args(): void
{
    $cid = $this->runContainer(['SAIL_LOG_MODE' => 'stdout']);
    sleep(3);
    $run = $this->execInContainer($cid, ['cat', '/etc/s6-overlay/s6-rc.d/php-fpm-log/run']);
    $this->assertMatchesRegularExpression('#s6-log\s+-b\s+n20\s+s10000000\s+T\s+!"gzip -nq9"\s+p\[php-fpm\]\s+1#', $run);
    $this->assertStringNotContainsString('/var/log/php-fpm', $run);
}

public function test_invalid_mode_fails_fast(): void
{
    $p = new Process([
        'docker', 'run', '--rm',
        '-e', 'SAIL_LOG_MODE=bogus',
        self::$imageTag,
    ], null, null, null, 30);
    $p->run();
    $this->assertNotSame(0, $p->getExitCode());
    $combined = $p->getOutput().$p->getErrorOutput();
    $this->assertStringContainsString("invalid SAIL_LOG_MODE='bogus'", $combined);
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter "php_fpm_log_|invalid_mode" -v`
Expected: FAIL.

- [ ] **Step 3: Rewrite `runtimes/8.x/s6/app/php-fpm-log-prepare/up`**

The current file is execlineb-scripted. Rewrite in shell to match nginx-log-prepare and add the templating logic:

```sh
#!/bin/sh
set -e

mkdir -p /var/log/php-fpm /var/log/php
chown -R sail:sail /var/log/php-fpm /var/log/php
chmod 02755 /var/log/php-fpm /var/log/php

MODE="${SAIL_LOG_MODE:-both}"
ARCHIVES="${SAIL_LOG_MAX_ARCHIVES:-20}"
SIZE="${SAIL_LOG_ROTATE_SIZE:-10000000}"

case "$MODE" in
  stdout) DEST='p[php-fpm] 1' ;;
  file)   DEST='/var/log/php-fpm p[php-fpm]' ;;
  both)   DEST='/var/log/php-fpm p[php-fpm] 1' ;;
  *)
    echo "php-fpm-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

cat > /etc/s6-overlay/s6-rc.d/php-fpm-log/run <<RUN_SCRIPT
#!/bin/sh
exec s6-log -b n${ARCHIVES} s${SIZE} T !"gzip -nq9" ${DEST}
RUN_SCRIPT
chmod +x /etc/s6-overlay/s6-rc.d/php-fpm-log/run

echo php-fpm-log-prepare completed > /tmp/php-fpm-log-prepare-ran
```

- [ ] **Step 4: Replace `runtimes/8.x/s6/app/php-fpm-log/run` with a stub**

```sh
#!/bin/sh
# Regenerated by php-fpm-log-prepare at container start based on SAIL_LOG_*
# env vars. Fallback body matches the default-both mode.
exec s6-log -b n20 s10000000 T !"gzip -nq9" /var/log/php-fpm p[php-fpm] 1
```

- [ ] **Step 5: Rebuild + run tests**

```bash
docker image rm sail-s6-log-test:local || true
vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter "php_fpm_log_|invalid_mode" -v
```

Expected: 3 passing tests.

Full image test suite sanity:
Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php -v`
Expected: all prior tests still PASS.

- [ ] **Step 6: Commit**

```bash
git add runtimes/8.x/s6/app/php-fpm-log-prepare/up runtimes/8.x/s6/app/php-fpm-log/run tests/Integration/S6LogPipelineTest.php
git commit -m "feat(image): template php-fpm-log run from SAIL_LOG_* env

Converts the php-fpm-log-prepare oneshot from execlineb to shell
(matches nginx-log-prepare pattern) and adds mode/archives/size
template logic. Invalid SAIL_LOG_MODE fails fast with a clear error
before s6-log starts."
```

---

## Chunk 6: End-to-end validation

### Task 14: E2E — HTTP request lands in stdout and on disk in default mode

**Files:**
- Modify: `tests/Integration/S6LogPipelineTest.php`

- [ ] **Step 1: Add E2E test**

```php
public function test_http_request_appears_in_stdout_and_on_disk_by_default(): void
{
    $p = new Process([
        'docker', 'run', '-d', '--rm',
        '-p', '0:80',
        self::$imageTag,
    ]);
    $p->mustRun();
    $cid = trim($p->getOutput());
    $this->runningContainers[] = $cid;

    $portProc = new Process(['docker', 'port', $cid, '80']);
    $portProc->mustRun();
    $port = (int) preg_replace('/.*:/', '', trim($portProc->getOutput()));

    $deadline = time() + 15;
    $ready = false;
    while (time() < $deadline) {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($sock) {
            fclose($sock);
            $ready = true;
            break;
        }
        usleep(300_000);
    }
    $this->assertTrue($ready, "nginx did not listen on 127.0.0.1:$port within 15s");

    $marker = '/e2e-marker-'.uniqid();
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    @file_get_contents("http://127.0.0.1:$port$marker", false, $ctx);

    sleep(2);

    $logsProc = new Process(['docker', 'logs', $cid]);
    $logsProc->run();
    $logs = $logsProc->getOutput().$logsProc->getErrorOutput();
    $this->assertStringContainsString($marker, $logs, "stdout did not contain the request path");

    $onDisk = $this->execInContainer($cid, ['cat', '/var/log/nginx/current']);
    $this->assertStringContainsString($marker, $onDisk, "s6-log /var/log/nginx/current did not contain the request path");
}
```

- [ ] **Step 2: Run, expected PASS**

Run: `vendor/bin/phpunit tests/Integration/S6LogPipelineTest.php --filter test_http_request_appears -v`
Expected: PASS. If it fails:
1. Increase `sleep(2)` to `sleep(4)` — s6-log may not have flushed.
2. Container exit quickly because nginx errors on a missing `/var/www` — fine, nginx still logs the request line. The test only checks for the path in logs, not a 200 response.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/S6LogPipelineTest.php
git commit -m "test(image): E2E verify default-both produces stdout + on-disk logs"
```

---

## Chunk 7: Documentation

### Task 15: Update CHANGELOG with behavior-change callouts

**Files:**
- Modify or create: `CHANGELOG.md`

- [ ] **Step 1: Check existing CHANGELOG conventions**

Run: `ls CHANGELOG.md 2>/dev/null && head -30 CHANGELOG.md`
Expected: either content or missing.

Also check whether the repo uses Release Please or conventional commits tooling:
Run: `grep -r "release-please" .github/ 2>/dev/null | head -5`

If Release Please is in use, the tool synthesizes CHANGELOG from commit messages — the plan's commit messages in prior tasks already follow the right shape. Skip to Step 3.

- [ ] **Step 2: If no automation, prepend to CHANGELOG.md**

```markdown
## Unreleased

### Breaking

- **`kubectl logs` output format changes.** Replaces the `[filename] ...` prefix emitted by the retired `logger` service with `[nginx] ...` / `[php-fpm] ...` prefixes from `s6-log`.
- **`/var/log/nginx/nginx.access.log` and `/var/log/nginx/nginx.error.log` no longer exist.** Rotated, gzipped archives now live at `/var/log/nginx/current` and `/var/log/nginx/@<tai64n>.s.gz`. Consumers with log-forwarders pointed at the old paths must update.
- **Laravel `storage/logs/laravel.log` no longer written by default.** `LOG_CHANNEL` defaults to `stderr`. Consumers relying on the on-disk file set `sail.app.logChannel: stack` or `daily` in their values.
- **Default container resources added** (CPU: 100m/1000m, Memory: 256Mi/1Gi). Consumers on capacity-constrained clusters may see scheduling pressure on first redeploy; set `resources` overrides in their values to mitigate.
- **The `logger` s6 service has been removed** (was the tail-and-prefix-to-stdout workaround for the broken log pipeline).

### Added

- `sail.logging.mode` values key — one of `stdout`, `file`, `both` (default). Controls whether nginx + php-fpm logs go to stdout, to rotated on-disk files, or both.
- `sail.logging.maxFileSize` (bytes) and `sail.logging.maxArchives` — s6-log rotation tuning.
- `sail.app.logChannel`, `sail.app.cacheDriver`, `sail.app.sessionDriver` — shipped as a chart-owned defaults Secret, overrideable by the consumer's `<name>-environment` secret (envFrom later-entry-wins).
- `CACHE_DRIVER=redis` and `SESSION_DRIVER=redis` are set automatically when `redis.secret` is configured.

### Fixed

- **Unbounded nginx access log growth** (298Mi/day/pod observed in production). nginx and php-fpm now write to stdout/stderr, feeding the already-wired s6-log producer-consumer pipeline with tee to rotated file + stdout.
- **`nginx-log` missing dependency on `nginx-log-prepare`** — could cause a race where the log longrun started before its prepare oneshot had written `/var/log/nginx`.
```

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: changelog for sail resource hygiene release

Documents the breaking behavior changes and new values surface."
```

---

## Chunk 8: Final verification

### Task 16: Run full test suite, lint chart, dry-apply

**Files:**
- (none — verification only)

- [ ] **Step 1: Run all feature tests**

Run: `vendor/bin/phpunit tests/Feature/ -v`
Expected: all HelmTemplateTest tests PASS plus any prior feature tests (BuildCommandTest).

- [ ] **Step 2: Run all integration tests**

Run: `vendor/bin/phpunit tests/Integration/ -v`
Expected: all S6LogPipelineTest tests PASS plus FullBuildTest.

- [ ] **Step 3: Run PHPStan**

Run: `vendor/bin/phpstan analyse src --memory-limit=512M`
Expected: no errors.

- [ ] **Step 4: Lint Helm chart**

Run: `helm lint stubs/helm/`
Expected: no errors. (Pre-existing `<project-name>` / `<host>` placeholder warnings are not this spec's concern.)

- [ ] **Step 5: Render the chart against a representative production values file**

Create a synthetic values file matching booklet/gta-events/reyemtech shape:

```yaml
# /tmp/sample-values.yaml
name: test-app
secret:
  enabled: true
  path: secret/test
  store: vault
database:
  secret: test-db-secret
  connection: pgsql
redis:
  secret: test-redis-secret
s3:
  secret: test-s3-secret
ingress:
  hosts:
    - host: test.example.com
      paths:
        - path: /
          pathType: ImplementationSpecific
```

Run:
```bash
helm template test stubs/helm/ -f /tmp/sample-values.yaml > /tmp/rendered.yaml
kubectl apply --dry-run=client -f /tmp/rendered.yaml
```

Expected: no validation errors.

- [ ] **Step 6: Sanity check envFrom ordering in rendered output**

Run:
```bash
grep -A 3 'envFrom:' /tmp/rendered.yaml | head -20
```

Expected output includes both secretRefs in correct order:

```
envFrom:
  - secretRef:
      name: test-app-defaults
  - secretRef:
      name: test-app-environment
```

- [ ] **Step 7: Sanity check SAIL_LOG_* env vars present**

Run:
```bash
grep -A 1 'SAIL_LOG_' /tmp/rendered.yaml
```

Expected: 3 entries each for web + worker (6 total) with default values `both` / `20` / `10000000`.

- [ ] **Step 8: No commit** — verification only.

---

## Cross-Repo Follow-Up (not part of this plan)

After the sail release ships and has been running for ≥ 24h in the `apps` namespace with no eviction events:

1. In `iac/src/index.ts`, delete the temporary `namespacePolicies.apps` block introduced by nimbus spec 1:

```ts
// DELETE this block
nimbus.configure({
  namespacePolicies: {
    apps: {
      limitRange: {
        defaultRequest: { ephemeralStorage: "1Gi" },
        defaultLimit:   { ephemeralStorage: "5Gi" },
      },
    },
  },
});
```

2. Run `pulumi up` in iac.
3. Verify: `kubectl describe limitrange default-limits -n apps` shows `500Mi/2Gi` defaults (the universal `DEFAULT_NAMESPACE_POLICY`).
4. No pod restarts. LimitRange defaults apply at admission only.

This step is tracked separately and intentionally NOT automated as part of this plan.

---

## Scope Notes

**Runtime coverage (open item 1–2 from spec).** This plan scopes to `runtimes/8.x/` only. If `runtimes/7.x/` or similar still exists at implementation time, that's a follow-up — the nginx/php-fpm/s6 fix pattern is self-contained and can be re-applied per runtime version. No runtime-shared code to factor.

**Chart versioning (open item 6 from spec).** This change is a **major** version bump for the chart because `kubectl logs` output format changes, the `LOG_CHANNEL` default changes, and the on-disk log file paths disappear. Bump `stubs/helm/Chart.stub` `version:` from the current value to the next major at release time (if the repo's Release Please / manual tooling doesn't handle this automatically — check `.github/workflows/release.yml` for convention). This is handled at release time, not inside a plan task, because the repo's tooling likely manages it.



