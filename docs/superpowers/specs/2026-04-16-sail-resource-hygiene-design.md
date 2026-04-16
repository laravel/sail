# Sail Resource Hygiene — Design

**Status:** Approved, awaiting implementation plan
**Date:** 2026-04-16
**Related:** `nimbus/docs/superpowers/specs/2026-04-15-disk-pressure-mitigation-design.md` (spec 1) — that spec installed per-pod ephemeral-storage caps via LimitRange; the `apps` namespace currently carries a temporary `1Gi/5Gi` override that exists because of gaps in this chart/image. The goal of this spec is to close those gaps so the override can be removed.

## Problem

Laravel pods built from `reyemtech/sail`'s `runtimes/8.x/` image and deployed via its Helm chart accumulate ephemeral storage on the node root disk. Empirically measured on 2026-04-16 in `reyemtech-iad-1`:

| Pod (age 16–35h) | Total ephemeral | Container rootfs writable layer | of which `/var/log/nginx/nginx.access.log` |
|---|---:|---:|---:|
| `booklet-web` | 339Mi | 328Mi | 298Mi (growing ~8.5Mi/hour) |
| `reyemtech-web` | ~340Mi | ~330Mi | ~300Mi |
| `gta-events-web` | ~340Mi | ~330Mi | ~300Mi |

The **dominant unbounded writer is nginx access logs in `/var/log/nginx/nginx.access.log`**, closely followed by php-fpm logs in `/var/log/php/`. Laravel's own `storage/logs/laravel.log` contributes <1Mi/day — not the cause. Extrapolating the nginx rate, one web pod would produce ~1.4Gi of ephemeral log after one week and ~6Gi after a month, comfortably exceeding spec 1's default 2Gi LimitRange cap and eventually triggering eviction.

### Why it's happening: a disconnected s6 pipeline

The image's s6-overlay setup already defines the correct pipeline in `runtimes/8.x/s6/app/`:

```
nginx service (longrun)          ──producer-for──▶ nginx-log
nginx-log service (longrun)      ──consumer-for──▶ nginx
nginx-log/run:
  exec s6-log -b n20 s10000000 T !"gzip -nq9" /var/log/nginx p[nginx] 1
                                                  ^^^^^^^^^^^^^^^^^ ^
                                                  rotated dir        tee to fd 1 (stdout)
```

An identical pair exists for `php-fpm`/`php-fpm-log`. This means s6-log is ALREADY configured to tee: write rotated, gzipped archives to disk (`/var/log/nginx/current`, `@<ts>.s.gz`) AND echo every line to stdout so kubelet captures it.

The pipeline is starved because the producers bypass it. nginx is built with `/etc/nginx/nginx.conf` + `/etc/nginx/http.d/default.conf` directives that write directly to file:

```
error_log  /var/log/nginx/nginx.error.log;
access_log /var/log/nginx/nginx.access.log;
```

nginx never writes to stdout — so the s6-log consumer gets nothing (`/var/log/nginx/current` is 0 bytes in the running pod). Same shape for php-fpm via `/etc/php/php-fpm.d/docker.conf`.

A `logger` s6 service exists as a workaround: a busy `while true; find -name "*.log" | tail -F | awk '{print "["f"] "$0}'` loop that tails whatever files it finds and prints to stdout so `kubectl logs` shows something. This produces no rotation and no ephemeral-storage relief.

### Chart-side gaps (secondary, not disk-driven)

Independent of the logging bug, the Helm chart has three hygiene gaps identified in spec 1's "Deferred" section:

1. No default `LOG_CHANNEL` env var — Laravel defaults to `stack`→`single` (file-based, unbounded) unless the consumer sets it via their environment secret.
2. No default `resources` block on web/worker/scheduler — pods deploy without requests/limits unless caller provides them, degrading scheduler accuracy and HPA behavior.
3. No default `CACHE_DRIVER` / `SESSION_DRIVER` when redis is plumbed via `redis.secret` — consumer must remember to set these explicitly in their environment secret or Laravel will fall back to `file` drivers.

These aren't currently causing measurable disk pressure (in-use apps already configure these via consumer env secret), but fixing them makes the chart safer for future consumers.

## Goals

- Eliminate the unbounded nginx + php-fpm log file growth by fixing the existing s6-log pipeline
- Make the logging destination **values-configurable** (`stdout` / `file` / `both`) so chart consumers who don't run cluster-level log aggregation still have on-disk rotated logs
- Retire the `logger` workaround service now that its job is done by s6-log's tee
- Default the Helm chart to safe values for `LOG_CHANNEL`, resources, and cache/session drivers
- Remove the temporary `apps` namespace LimitRange override in `iac/src/index.ts` (introduced by spec 1) once sail changes roll out

## Non-Goals

- Changing how Laravel queues, cron, or horizon log
- Replacing s6-overlay or introducing a different process supervisor
- Switching away from nginx or php-fpm
- Adding a log aggregation stack (Loki, Promtail, etc.) to the chart — consumers bring their own
- Changing the physical storage mount layout (no `emptyDir` at `storage/`, no PVCs for logs)
- Fixing bloat in other parts of the writable layer beyond `/var/log/` (none measured to matter)

## Architecture

Changes land in two places in `reyemtech/sail`:

```
runtimes/8.x/
├── nginx.conf                                      ← edited: log → stdout/stderr
├── http.d/default.conf                             ← edited: remove per-server log directives
├── php-fpm.conf                                    ← edited: error_log → fd/2
├── php-fpm.d/docker.conf                           ← edited: error_log + access.log → fd/2
├── s6/app/
│   ├── logger/                                     ← DELETED (workaround retired)
│   ├── nginx-log/run                               ← edited: s6-log args templated from env
│   ├── nginx-log-prepare/up                        ← edited: compute args, write run file
│   ├── php-fpm-log/run                             ← edited: s6-log args templated from env
│   └── php-fpm-log-prepare/up                      ← edited: compute args, write run file
stubs/helm/
├── templates/_spec.stub                            ← edited: add default env, resources, log config
├── templates/_helpers.tpl                          ← edited: new helper for log env vars
└── values.yaml (consumer-authored; the chart ships defaults via values.schema.json or fallback expressions)
```

Three separable concerns, all in the same spec because they ship as one image+chart release:

1. **Image: log pipeline wiring** — fix nginx/php-fpm configs, retire `logger`, template s6-log args.
2. **Chart: logging values surface** — new `sail.logging.*` values block, piped to env vars the image's `-prepare` oneshots consume.
3. **Chart: hygiene defaults** — `LOG_CHANNEL`, resource blocks, conditional cache/session drivers.

## Detailed Changes

### 1. Image log pipeline (primary fix)

#### 1a. `runtimes/8.x/nginx.conf`

Today (lines 5–6):

```nginx
error_log  /var/log/nginx/nginx.error.log;
access_log /var/log/nginx/nginx.access.log;
```

After:

```nginx
error_log  /dev/stderr warn;
access_log /dev/stdout main;
```

The `access_log off;` directives for health/metrics locations (lines 58, 64, 84) stay unchanged — those suppress per-request access logging for noise, independent of destination.

#### 1b. `runtimes/8.x/http.d/default.conf`

Today (from running pod):

```nginx
error_log  /var/log/nginx/nginx.error.log;
access_log /var/log/nginx/nginx.access.log;
```

After: **delete these two lines.** The `server` block inherits from the `http` block in `nginx.conf`. Per-location `access_log off;` lines stay.

#### 1c. `runtimes/8.x/php-fpm.conf`

Today (line 2):

```ini
error_log = /var/log/php/php-fpm.err.log
```

After:

```ini
error_log = /proc/self/fd/2
```

(php-fpm does not accept `/dev/stderr` directly in all configurations — `/proc/self/fd/2` is the portable equivalent and the documented container pattern.)

#### 1d. `runtimes/8.x/php-fpm.d/docker.conf`

Today:

```ini
error_log = /var/log/php/php-fpm.err.log
access.log = /var/log/php/php-fpm.out.log
```

After:

```ini
error_log = /proc/self/fd/2
access.log = /proc/self/fd/2
```

#### 1e. Delete `runtimes/8.x/s6/app/logger/`

Remove:
- `logger/type`
- `logger/run`
- `logger/dependencies.d/php-fpm`

And remove the `logger` entry from any `contents.d/` user bundle that includes it (must be audited during plan phase).

**Why safe:** `logger` exists to tail `/var/log/**/*.log` to stdout. After 1a–1d, nginx and php-fpm no longer write to those files; s6-log's current file is named `current`, not `*.log`, so nothing of interest would be tailed anyway. Any custom files a consumer places under `/var/log` with a `.log` extension would stop being mirrored to stdout — this is called out as a behavior change in "Rollout" below.

#### 1f. Template `nginx-log/run` via `nginx-log-prepare`

Today (static):

```bash
#!/bin/sh
exec s6-log -b n20 s10000000 T !"gzip -nq9" /var/log/nginx p[nginx] 1
```

After — `nginx-log-prepare/up` computes the args at container start based on env vars, writes the `run` file (which s6 then execs):

```bash
#!/bin/sh
set -e
mkdir -p /var/log/nginx
chown root:root /var/log/nginx
chmod 02755 /var/log/nginx

MODE="${SAIL_LOG_MODE:-both}"
ARCHIVES="${SAIL_LOG_MAX_ARCHIVES:-20}"
SIZE="${SAIL_LOG_ROTATE_SIZE:-10000000}"

case "$MODE" in
  stdout) DEST='p[nginx] 1' ;;
  file)   DEST="/var/log/nginx p[nginx]" ;;
  both)   DEST="/var/log/nginx p[nginx] 1" ;;
  *)
    echo "nginx-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

cat > /etc/s6-overlay/s6-rc.d/nginx-log/run <<EOF
#!/bin/sh
exec s6-log -b n${ARCHIVES} s${SIZE} T !"gzip -nq9" ${DEST}
EOF
chmod +x /etc/s6-overlay/s6-rc.d/nginx-log/run
```

Service-tree graph (`producer-for`, `consumer-for`, `pipeline-name`) is unchanged — only the `run` file content is re-generated from env at container start. A `dependencies.d/nginx-log-prepare` entry **must be added** on `nginx-log/` (today only `php-fpm-log/` has its analogous dependency). Without it the `-log` longrun may start before its `-prepare` oneshot writes the run file.

#### 1g. Template `php-fpm-log/run` via `php-fpm-log-prepare`

Same pattern as 1f, with `DEST` variants pointing at `/var/log/php-fpm` and prefix `p[php-fpm]`. Update `php-fpm-log-prepare/up` (currently execlineb-scripted) — convert to shell or add a second execlineb stage; detail resolved in plan phase. The s6 graph wiring is unchanged.

### 2. Chart logging values surface

New Helm values block:

```yaml
sail:
  logging:
    mode: both                    # stdout | file | both
    maxFileSize: 10000000         # bytes (raw, no suffix — fed directly to s6-log -s)
    maxArchives: 20               # s6-log -n argument
```

**Note:** `maxFileSize` is in raw bytes because s6-log's `-s` argument takes a raw integer. Helm doesn't have a canonical Kubernetes-style quantity parser we'd piggyback on here; rendering `10000000` is simpler than building a parser. Consumers who want 20Mi set `20000000`.

#### 2a. `stubs/helm/templates/_spec.stub` — env injection

Add to the `env:` array (inside the `$hasInfraSecrets` block, or unconditionally if there's no convenient existing conditional — plan phase decides placement):

```yaml
- name: SAIL_LOG_MODE
  value: {{ .main.logging.mode | default "both" | quote }}
- name: SAIL_LOG_MAX_ARCHIVES
  value: {{ .main.logging.maxArchives | default 20 | quote }}
- name: SAIL_LOG_ROTATE_SIZE
  value: {{ .main.logging.maxFileSize | default 10000000 | quote }}
```

These are read by `nginx-log-prepare` / `php-fpm-log-prepare` at container start.

Per-mode effective behavior:

| `sail.logging.mode` | nginx/php-fpm write to | s6-log writes to | kubectl logs sees | Ephemeral cost (per service, bounded) |
|---|---|---|---|---|
| `both` (default) | stdout/stderr | `/var/log/nginx/` rotated + stdout tee | yes (via s6-log tee) | ~`maxArchives × maxFileSize × gzip ratio` ≈ 20-50Mi |
| `stdout` | stdout/stderr | stdout only | yes | 0 (excluding kubelet's own rotation, which is already accounted separately) |
| `file` | stdout/stderr | `/var/log/nginx/` rotated only | no | ~20-50Mi |

### 3. Chart hygiene defaults

#### 3a. `LOG_CHANNEL` default (intervention A)

New values block:

```yaml
sail:
  app:
    logChannel: stderr            # Laravel LOG_CHANNEL value; consumer overridable
```

`_spec.stub` env injection:

```yaml
- name: LOG_CHANNEL
  value: {{ .main.app.logChannel | default "stderr" | quote }}
```

**Override semantics (Kubernetes):** `env` entries take precedence over `envFrom` when the same variable is set in both. That means putting `LOG_CHANNEL` in `env` would **prevent** a consumer's `<name>-environment` secret from overriding it — the opposite of what we want.

Therefore chart defaults for overrideable variables (`LOG_CHANNEL`, `CACHE_DRIVER`, `SESSION_DRIVER`) are NOT emitted via `env`. Instead the chart generates a sibling secret `<name>-defaults` containing the defaults as key/value pairs and prepends it to the `envFrom` list:

```yaml
envFrom:
  - secretRef:
      name: {{ include "sail.name" . }}-defaults       # chart-owned, applied first
  - secretRef:
      name: {{ include "sail.name" . }}-environment    # consumer-authored, overrides
```

Among multiple `envFrom` entries that set the same key, **later entries override earlier ones** — so the consumer's environment secret always wins over the chart's defaults. This is the sole mechanism for all values-configurable chart defaults in this spec; `env:` remains reserved for non-overrideable plumbing (DB_HOST, REDIS_HOST, AWS_BUCKET, etc. — which come from dedicated secrets, not the consumer's environment secret).

The `SAIL_LOG_MODE` / `SAIL_LOG_MAX_ARCHIVES` / `SAIL_LOG_ROTATE_SIZE` env vars from section 2a are **not** consumer-overrideable at the container level — they're chart-render-time values, so they go in `env:` directly.

With `LOG_CHANNEL=stderr`, Laravel writes logs to php-fpm's stderr → captured by the php-fpm-log s6 pipeline → file + stdout per `sail.logging.mode`. `storage/logs/laravel.log` is no longer written to.

#### 3b. Default `resources` block (intervention B)

`_spec.stub` currently renders resources only `{{- with .resources }}` — no defaults. Add a `_helpers.tpl` helper:

```go
{{- define "sail.resources" -}}
{{- if .tierResources -}}
{{- toYaml .tierResources -}}
{{- else -}}
requests:
  cpu: 100m
  memory: 256Mi
limits:
  cpu: 1000m
  memory: 1Gi
{{- end -}}
{{- end -}}
```

Rendered in `_spec.stub`:

```yaml
resources:
  {{- include "sail.resources" (dict "tierResources" .resources) | nindent 12 }}
```

**Ephemeral-storage is deliberately NOT set** in the default — spec 1's LimitRange default injects `500Mi/2Gi` from the namespace, which is the intended source of truth. Setting it here would override the cluster-managed default. Consumers who need different ephemeral caps set their own `resources.requests/limits.ephemeral-storage` explicitly.

Values:
- CPU: 100m request / 1000m limit — comfortable for a Laravel web worker under moderate load
- Memory: 256Mi request / 1Gi limit — covers PHP memory_limit=256M (the sail default) + nginx + opcache; Horizon workers typically within this
- Consumer can override per-tier (`web.resources`, `worker.resources`, `scheduler.resources`) or globally (`global.resources` — plan phase: introduce if not present)

#### 3c. Conditional cache/session drivers (intervention C)

`_spec.stub` env injection inside the `{{- if .main.redis.secret }}` block (which already exists and is where DB_* / REDIS_* env is wired):

```yaml
- name: CACHE_DRIVER
  value: {{ .main.app.cacheDriver | default "redis" | quote }}
- name: SESSION_DRIVER
  value: {{ .main.app.sessionDriver | default "redis" | quote }}
```

Only rendered when `redis.secret` is set. Consumers who want `file` or `database` cache/session deliberately set `sail.app.cacheDriver` / `sail.app.sessionDriver` explicitly.

## Behavior

### New pod, default values, after rollout

1. Image starts; s6 init runs.
2. `nginx-log-prepare` reads env → writes `/etc/s6-overlay/s6-rc.d/nginx-log/run` with `s6-log -b n20 s10000000 T !"gzip -nq9" /var/log/nginx p[nginx] 1`.
3. `php-fpm-log-prepare` does the same for php-fpm-log.
4. `nginx` longrun starts, writes `[DATE] 10.x.x.x - - "GET / HTTP/1.1" 200 ...` to stdout.
5. s6 piping wires nginx's stdout into nginx-log's stdin.
6. s6-log writes to `/var/log/nginx/current` (rotated at 10Mi, 20 archives gzipped) AND echoes to fd 1.
7. Fd 1 of nginx-log bubbles up to PID 1 (s6-svscan) stdout.
8. kubelet captures s6-svscan stdout, rotates to container log on node.
9. `kubectl logs <pod> -c web` shows nginx access log lines interleaved with php-fpm error log lines and Laravel app log lines (via `LOG_CHANNEL=stderr` → php-fpm stderr).

**Worker and scheduler tiers:** these run `php artisan queue:work` or Horizon directly as the container's main process (see `_spec.stub` lines 160–165) — no nginx, no php-fpm, no s6 log pipeline consuming their output. Their stdout/stderr go straight to PID 1, picked up by kubelet. The chart's `sail.logging.*` values only affect the web tier's s6-log behavior; worker/scheduler get `LOG_CHANNEL=stderr` via the defaults secret and that's the complete story for them.

### Existing pod behavior unchanged until redeploy

Changes are image-level and chart-level — pods pick them up only when their deployment rolls. No mid-flight disruption.

### Values-configurable matrix

| `sail.logging.mode` | Use case | Expected ephemeral usage per pod |
|---|---|---|
| `both` (default) | General — works with and without cluster log aggregation | ~40-100Mi (bounded by maxArchives × maxFileSize, compressed) |
| `stdout` | Clusters with Loki / Fluentbit / any stdout-ingesting log pipeline | ~12Mi (kubelet's own rotation only) |
| `file` | Air-gapped clusters, consumers forwarding logs via file watchers | ~40-100Mi, `kubectl logs` silent for nginx/php-fpm |

## Rollout & Rollback

### Rollout order

1. **Image rebuild** — merge sail PR, publish `ghcr.io/reyemtech/sail-*:X.Y.Z` images with the fixes in 1a–1g. Chart defaults from section 2/3 ship in the same release (single version bump for both image + chart to stay in lockstep).
2. **Per-app rollout** — each Laravel consumer (booklet, gta-events, reyemtech, plus any external consumers) bumps their `image.tag` and chart version. No consumer code changes required — defaults take effect.
3. **Verify** — on one app first: `kubectl exec` in, `ls -la /var/log/nginx/` should show `current` growing and `nginx.access.log` gone (or zero). `kubectl logs` shows nginx lines with `[nginx]` prefix. Node ephemeral-usage trends flat or down.
4. **Remove `apps` LimitRange override** — once all apps-namespace pods are on the new image for ≥ 24h with no eviction events, delete the `namespacePolicies.apps` block in `iac/src/index.ts` and `pulumi up`. Verification: `kubectl describe limitrange default-limits -n apps` shows `500Mi/2Gi` default. No pod restarts triggered by this — LimitRange defaults apply at admission only.

### Rollback

Per-consumer rollback = `helm upgrade` to the previous chart version + previous image tag. Zero-downtime.

If a specific change is bad but the rest is fine, consumers can override via values (e.g., `sail.logging.mode: file` forces disk-only if stdout ingestion is breaking, `sail.app.logChannel: stack` restores the Laravel default file-based logger).

### Behavior-change callouts for consumers

The chart's `CHANGELOG.md` / release notes must call out:

- **`kubectl logs` output changes shape.** Previously all `*.log` files were tailed by the `logger` service with `[filename] ` prefix. After this change, stdout shows nginx + php-fpm + Laravel app logs with `[nginx] [php-fpm]` prefix and in the order they're written. Simpler but different.
- **`/var/log/nginx/nginx.access.log` and `nginx.error.log` no longer exist.** On-disk content moves to `/var/log/nginx/current` + rotated archives (`@<tai64n>.s.gz`). Consumers with log-forwarder config pointed at the old filenames must update.
- **Laravel's `storage/logs/laravel.log`** is no longer written to by default (LOG_CHANNEL defaults to `stderr`). Consumers relying on this file set `sail.app.logChannel: stack` or `daily` explicitly.
- **Default resource requests/limits added.** Consumers whose clusters are at-capacity may see scheduling pressure on first redeploy; mitigation is to set explicit resources overrides in their values.

## Testing

### Image tests

Add a new smoke test in the sail CI pipeline (plan phase resolves exact path — presumably `.github/workflows/test-image.yml` or similar):

1. **Container boot test** — build image, run as container, wait for s6-svscan ready, `docker exec`:
   - `ps axo pid,comm,args` shows `s6-log` processes with correct args for default mode
   - `s6-log` args match regex `s6-log -b n20 s10000000 T .* /var/log/nginx p\[nginx\] 1` (both mode)
   - `/var/log/nginx/current` exists, owned correctly
   - `/etc/s6-overlay/s6-rc.d/logger/` does NOT exist
2. **Mode variant tests** — boot with `SAIL_LOG_MODE=stdout` and `SAIL_LOG_MODE=file`, assert s6-log args match the expected variant.
3. **Invalid mode test** — boot with `SAIL_LOG_MODE=invalid`, assert container exits with non-zero and nginx-log-prepare logs the error.
4. **Log flow test** — issue an HTTP request into the container, wait 1s, assert:
   - `docker logs` contains a line matching the request path
   - In `both`/`file` mode: `/var/log/nginx/current` has grown past 0 bytes

### Chart tests

Existing `stubs/helm` test harness (if any — plan phase checks). Add cases:

1. **Default render** — `helm template` with no values produces a Deployment with default resources, `LOG_CHANNEL=stderr`, `SAIL_LOG_MODE=both`, `SAIL_LOG_MAX_ARCHIVES=20`.
2. **Resource override** — values `web.resources.requests.cpu: 500m` produces that in the render.
3. **Redis cache/session** — render with `redis.secret: my-redis` includes `CACHE_DRIVER=redis` + `SESSION_DRIVER=redis`; render without does not.
4. **Logging mode override** — `sail.logging.mode: stdout` produces `SAIL_LOG_MODE=stdout` in env.
5. **Consumer log channel override** — `sail.app.logChannel: daily` produces `LOG_CHANNEL=daily`.

### Manual cluster verification (post-`helm upgrade`)

1. `kubectl exec -n apps <new-pod> -c web -- ls -la /var/log/nginx/` → `current` present, `nginx.access.log` absent.
2. `kubectl exec -n apps <new-pod> -c web -- ps axo comm,args | grep s6-log` → shows correct tee args.
3. `kubectl exec -n apps <new-pod> -c web -- ps axo comm,args | grep -c logger` → 0 (service gone).
4. `kubectl logs -n apps <new-pod> -c web --tail=50` → mix of `[nginx]`-prefixed and `[php-fpm]`-prefixed lines.
5. After 24h: `kubectl exec -n apps <new-pod> -c web -- du -sh /var/log/` → well under 100Mi total.
6. After 24h: kubelet stats summary → pod ephemeral usage stable under 100Mi (down from ~340Mi baseline).

## Deferred

### ephemeralStorage resource default (intervention B-extended)

Spec 1's LimitRange injects `500Mi / 2Gi` ephemeral-storage defaults automatically. Setting the chart's resource default to include ephemeralStorage would override that with a different value. We deliberately leave it to the LimitRange until/unless a sail consumer explicitly requests a chart-level ephemeral-storage default.

### `emptyDir` at `storage/` (originally D)

Measured `storage/` usage across all Laravel pods is < 10Mi. The intervention addresses a hypothetical unbounded writer that doesn't exist empirically. If v1 rollout surfaces a real case (e.g., a consumer with a large `storage/app/` upload cache), revisit via its own spec.

### logrotate sidecar (originally E)

Redundant with s6-log tee and kubelet stdout rotation. Dropped.

## Open Items for Plan Phase

1. **Audit all `runtimes/*/` variants** — `Dockerfile.app`, `Dockerfile.app-build`, `Dockerfile.production`, `Dockerfile.s6`, `Dockerfile.base`. Confirm the nginx.conf / php-fpm.conf / s6 changes apply to all that are actually used downstream, and that none of them redefine the files we're editing later in the build.
2. **Audit `runtimes/*/` for versions other than `8.x/`** — if there's a `7.x/` or future `9.x/`, decide whether this spec covers them or is scoped to `8.x/` only. Recommend scoping to `8.x/` unless forced otherwise.
3. **Verify `envFrom` vs `env` precedence** for chart-default env vars. If defaults must be overrideable by the consumer's `<name>-environment` secret, confirm the injection order works; if not, fall back to emitting defaults in a chart-owned secret that envFrom'd first, with consumer's secret envFrom'd last.
4. **Confirm `contents.d/` cleanup** — when deleting `logger/`, verify it's not referenced by a `user` or `user2` bundle's `contents.d/logger` file. If it is, remove that too.
5. **php-fpm-log-prepare conversion** — currently execlineb-scripted. Decide: keep execlineb (port the logic in-language) or switch to shell (matches nginx-log-prepare). Recommend shell for maintainability; plan phase confirms.
6. **Chart versioning policy** — does this warrant a major bump (SemVer — behavior changes affecting consumers on upgrade) or a minor? Recommend major given the `kubectl logs` shape change and file-path removal. Plan phase confirms with repo conventions.

## Files Changed (Summary)

| File | Change | Approx LOC |
|---|---|---:|
| `runtimes/8.x/nginx.conf` | log directive → stdout/stderr | ±2 |
| `runtimes/8.x/http.d/default.conf` | remove per-server log directives | -2 |
| `runtimes/8.x/php-fpm.conf` | error_log → fd/2 | ±1 |
| `runtimes/8.x/php-fpm.d/docker.conf` | error_log + access.log → fd/2 | ±2 |
| `runtimes/8.x/s6/app/logger/` | delete (3 files) | -15 |
| `runtimes/8.x/s6/app/{nginx,php-fpm}-log-prepare/up` | template args from env | +40 |
| `runtimes/8.x/s6/app/{nginx,php-fpm}-log/run` | generated at boot | (unchanged in repo; written at runtime) |
| `runtimes/8.x/s6/app/user/contents.d/logger` (if present) | delete | -1 |
| `stubs/helm/templates/_spec.stub` | env defaults: LOG_CHANNEL, SAIL_LOG_*, CACHE/SESSION_DRIVER; resources helper | +40 |
| `stubs/helm/templates/_helpers.tpl` | `sail.resources` helper, logging helpers | +25 |
| `stubs/helm/values.yaml` (sample in repo or README) | document new `sail.logging.*`, `sail.app.*` blocks | +15 |
| `CHANGELOG.md` / release notes | behavior-change callouts | +20 |
| Image tests | boot-smoke + mode variants | +60 |
| Chart tests | render assertions | +40 |
| **Total** | | **~260** |

## Cross-Repo Work (informational, not shipped in this spec)

Once this spec ships and apps have rolled onto the new image for ≥ 24h:

```ts
// iac/src/index.ts — DELETE this block (introduced in spec 1)
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

Verification: `kubectl describe limitrange default-limits -n apps` falls back to `500Mi/2Gi`. No pod disruption (LimitRange applies at admission only).
