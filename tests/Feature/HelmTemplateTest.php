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
        // The baseline values below replace unfilled placeholder strings in values.stub
        // (e.g. `ghcr.io/<organization>/<package>`, `<host>`) so helm template renders
        // without errors. Individual tests may further override via $valuesOverrides.
        File::put($valuesFile, \Symfony\Component\Yaml\Yaml::dump(array_merge([
            'name' => 'testapp',
            'secret' => ['enabled' => false, 'path' => '/fake', 'store' => 'fake'],
            'web' => ['image' => ['repository' => 'ghcr.io/testorg/testapp-web']],
            'worker' => ['image' => ['repository' => 'ghcr.io/testorg/testapp-worker']],
            'ingress' => ['hosts' => [['host' => 'testapp.example.com', 'paths' => [['path' => '/', 'pathType' => 'ImplementationSpecific']]]]],
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

    public function test_defaults_secret_renders_when_app_values_is_null(): void
    {
        // Regression: `app: null` in consumer values would panic helm template
        // if the template didn't defend against a nil .Values.app map.
        $out = $this->renderChart(['app' => null]);
        $this->assertMatchesRegularExpression(
            '/name:\s*testapp-defaults.*?LOG_CHANNEL:\s*"?stderr"?/s',
            $out
        );
    }

    public function test_cache_session_absent_without_redis(): void
    {
        $out = $this->renderChart();
        $data = $this->extractSecretStringData($out, 'testapp-defaults');
        $this->assertArrayHasKey('LOG_CHANNEL', $data, 'testapp-defaults Secret not found or stringData not parsed');
        $this->assertArrayNotHasKey('CACHE_DRIVER', $data);
        $this->assertArrayNotHasKey('SESSION_DRIVER', $data);
    }

    public function test_cache_session_present_with_redis(): void
    {
        $out = $this->renderChart(['redis' => ['secret' => 'my-redis']]);
        $data = $this->extractSecretStringData($out, 'testapp-defaults');
        $this->assertArrayHasKey('CACHE_DRIVER', $data);
        $this->assertArrayHasKey('SESSION_DRIVER', $data);
        $this->assertSame('redis', $data['CACHE_DRIVER']);
        $this->assertSame('redis', $data['SESSION_DRIVER']);
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

    public function test_envfrom_order_defaults_then_environment(): void
    {
        $out = $this->renderChart();

        // Three envFrom blocks must have this ordering: web Deployment, worker
        // Deployment, scheduler CronJob. A single-match assertion would miss a
        // regression in the scheduler stub.
        $matches = preg_match_all(
            '/envFrom:\s*'
                .'\n\s*-\s*secretRef:\s*'
                .'\n\s*name:\s*testapp-defaults\s*'
                .'\n\s*-\s*secretRef:\s*'
                .'\n\s*name:\s*testapp-environment/',
            $out
        );

        $this->assertSame(
            4,
            $matches,
            'Expected envFrom order (defaults then environment) in all 4 rendered locations (web, worker, scheduler, presync-migrate)'
        );
    }

    public function test_sail_log_env_vars_defaults(): void
    {
        $out = $this->renderChart();

        $this->assertSame(2, preg_match_all('/name:\s*SAIL_LOG_MODE\s*\n\s*value:\s*"both"/', $out),
            'SAIL_LOG_MODE=both must appear in both web and worker Deployments');
        $this->assertSame(2, preg_match_all('/name:\s*SAIL_LOG_MAX_ARCHIVES\s*\n\s*value:\s*"20"/', $out),
            'SAIL_LOG_MAX_ARCHIVES=20 must appear in both web and worker Deployments');
        $this->assertSame(2, preg_match_all('/name:\s*SAIL_LOG_ROTATE_SIZE\s*\n\s*value:\s*"10000000"/', $out),
            'SAIL_LOG_ROTATE_SIZE=10000000 must appear in both web and worker Deployments');
    }

    public function test_sail_log_env_vars_override(): void
    {
        $out = $this->renderChart([
            'logging' => ['mode' => 'stdout', 'maxArchives' => 5, 'maxFileSize' => 20000000],
        ]);

        $this->assertSame(2, preg_match_all('/name:\s*SAIL_LOG_MODE\s*\n\s*value:\s*"stdout"/', $out));
        $this->assertSame(2, preg_match_all('/name:\s*SAIL_LOG_MAX_ARCHIVES\s*\n\s*value:\s*"5"/', $out));
        $this->assertSame(2, preg_match_all('/name:\s*SAIL_LOG_ROTATE_SIZE\s*\n\s*value:\s*"20000000"/', $out));
    }

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

        // Top-level resources fallback should apply to BOTH web and worker deployments
        $this->assertSame(
            2,
            preg_match_all('/name:\s*testapp-(?:web|worker).*?resources:.*?cpu:\s*250m/s', $out),
            'Top-level resources fallback should be rendered in both web and worker Deployments'
        );
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

        // Web tier override wins
        $this->assertMatchesRegularExpression(
            '/name:\s*testapp-web.*?resources:\s*\n\s*requests:\s*\n\s*cpu:\s*500m/s',
            $out,
            'web.resources should override top-level'
        );

        // Worker tier (not overridden) keeps top-level fallback
        $this->assertMatchesRegularExpression(
            '/name:\s*testapp-worker.*?resources:.*?cpu:\s*250m/s',
            $out,
            'worker should still use top-level fallback when no worker.resources set'
        );
    }

    /**
     * Extract key-value pairs from the stringData block of a named Secret document
     * inside a multi-document rendered chart output.
     *
     * Uses regex rather than Symfony\Yaml::parse because sail.labels emits duplicate
     * app.kubernetes.io/instance keys (pre-existing _helpers.tpl bug) that the YAML
     * parser rejects with "Duplicate key detected".
     *
     * @return array<string, string>
     */
    protected function extractSecretStringData(string $yaml, string $name): array
    {
        $docs = preg_split('/^---$/m', $yaml);
        foreach ($docs as $doc) {
            if (! preg_match('/kind:\s*Secret\b/', $doc)) {
                continue;
            }
            if (! preg_match('/name:\s*'.preg_quote($name, '/').'(\s|$)/m', $doc)) {
                continue;
            }
            if (! preg_match('/^stringData:\s*\n((?:[ \t]+\S[^\n]*\n?)*)/m', $doc, $blockMatch)) {
                return [];
            }
            $result = [];
            preg_match_all('/^[ \t]+([\w_]+):\s*"?([^"\n]*)"?$/m', $blockMatch[1], $kvMatches, PREG_SET_ORDER);
            foreach ($kvMatches as $kv) {
                $result[trim($kv[1])] = trim($kv[2]);
            }
            return $result;
        }
        return [];
    }
}
