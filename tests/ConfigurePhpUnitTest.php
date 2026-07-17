<?php

namespace Laravel\Sail\Tests;

use Laravel\Sail\Console\InstallCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class ConfigurePhpUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink($this->app->basePath('phpunit.xml'));

        parent::tearDown();
    }

    #[DataProvider('phpUnitDatabaseEnvLineProvider')]
    public function test_it_replaces_the_in_memory_database_value_regardless_of_formatting($envLine)
    {
        file_put_contents($this->app->basePath('phpunit.xml'), <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit>
            <php>
                <env name="DB_CONNECTION" value="sqlite"/>
                {$envLine}
            </php>
        </phpunit>
        XML);

        $command = new InstallCommand();
        $command->setLaravel($this->app);

        $method = new ReflectionMethod($command, 'configurePhpUnit');
        $method->setAccessible(true);
        $method->invoke($command);

        $contents = file_get_contents($this->app->basePath('phpunit.xml'));

        $this->assertStringContainsString('<env name="DB_DATABASE" value="testing"/>', $contents);
        $this->assertStringNotContainsString(':memory:', $contents);
        $this->assertStringNotContainsString('DB_CONNECTION', $contents);
    }

    public static function phpUnitDatabaseEnvLineProvider()
    {
        return [
            'default skeleton formatting' => [
                '<env name="DB_DATABASE" value=":memory:"/>',
            ],
            'extra space before self-closing slash' => [
                '<env name="DB_DATABASE" value=":memory:" />',
            ],
            'commented out variant' => [
                '<!-- <env name="DB_DATABASE" value=":memory:"/> -->',
            ],
            'commented out variant with extra space' => [
                '<!-- <env name="DB_DATABASE" value=":memory:" /> -->',
            ],
        ];
    }
}
