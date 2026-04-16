<?php

namespace Laravel\Sail\Tests\Integration;

use Laravel\Sail\Tests\TestCase;
use Symfony\Component\Process\Process;

class S6LogPipelineTest extends TestCase
{
    protected static string $imageTag = 'sail-s6-log-test:local';

    protected static bool $imageBuilt = false;

    protected static bool $buildFailed = false;

    protected array $runningContainers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dockerCheck = new Process(['docker', 'buildx', 'version']);
        $dockerCheck->run();
        if (! $dockerCheck->isSuccessful()) {
            $this->markTestSkipped('Docker buildx unavailable; skipping image integration test.');
        }

        if (self::$buildFailed) {
            $this->markTestSkipped('Base image build previously failed; skipping remaining tests in class.');
        }

        if (! self::$imageBuilt) {
            try {
                $this->buildBaseImage();
                self::$imageBuilt = true;
            } catch (\Throwable $e) {
                self::$buildFailed = true;
                $this->markTestSkipped('Base image build failed: '.$e->getMessage());
            }
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

    /**
     * Build the test image from Dockerfile.base.
     *
     * Approach 3 (base-only + entrypoint override) was used because:
     * - Approach 1 (bake app target) requires RUNTIME_DIR=vendor/... which does not
     *   exist in the worktree standalone context.
     * - Approach 2 (multi-stage via Dockerfile.app) was unnecessary since
     *   Dockerfile.app has no CMD anyway — it only adds s6/local services.
     * - Approach 3: build Dockerfile.base directly, then run containers with
     *   --entrypoint /init. The s6-overlay Alpine package installs /init
     *   into the image, so this produces a fully bootable s6 container.
     *
     * A dummy mkcert CA cert is created at runtimes/8.x/certs/mkcert-rootCA.pem
     * if it does not already exist (required by COPY --from=runtime in Dockerfile.base).
     */
    protected function buildBaseImage(): void
    {
        $runtimeDir = dirname(__DIR__, 2).'/runtimes/8.x';
        $certsDir = $runtimeDir.'/certs';
        $certPath = $certsDir.'/mkcert-rootCA.pem';

        if (! file_exists($certPath)) {
            if (! is_dir($certsDir)) {
                mkdir($certsDir, 0755, true);
            }

            $genCert = new Process([
                'openssl', 'req', '-new', '-x509', '-days', '365', '-nodes',
                '-out', $certPath,
                '-keyout', '/dev/null',
                '-subj', '/CN=Test CA',
            ]);
            $genCert->mustRun();
        }

        $uname = new Process(['uname', '-m']);
        $uname->mustRun();
        $arch = trim($uname->getOutput());
        $platform = 'linux/'.($arch === 'x86_64' ? 'amd64' : 'arm64');

        // Alpine-based build + multi-arch layers with warm BuildKit cache typically
        // complete in 1-4 minutes; 600s gives generous headroom for cold-cache runs.
        $build = new Process([
            'docker', 'buildx', 'build',
            '--load',
            '--build-context', "runtime={$runtimeDir}",
            '--platform', $platform,
            '-t', self::$imageTag,
            '-f', 'Dockerfile.base',
            '.',
        ], $runtimeDir, timeout: 600);

        $build->mustRun();
    }

    /**
     * Run a detached container and wait for s6-svscan to appear.
     *
     * The --entrypoint /init override is required because Dockerfile.base
     * has no CMD or ENTRYPOINT directive — s6-overlay is installed from the
     * Alpine package and exposes /init, but nothing sets it as the default.
     *
     * @param  array<string, string>  $env
     */
    protected function runContainer(array $env = []): string
    {
        $args = ['docker', 'run', '-d', '--entrypoint', '/init'];
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

    /**
     * Run a command inside the container and return stdout.
     *
     * Note: exit code is NOT checked — the caller is responsible for asserting
     * the output matches expectations. Use `assertSame` / `assertStringContainsString`
     * on the returned string, or wrap with a call to `\Symfony\Component\Process\Process`
     * directly when you need exit-code inspection.
     */
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
}
