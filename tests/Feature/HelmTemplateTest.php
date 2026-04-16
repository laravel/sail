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
}
