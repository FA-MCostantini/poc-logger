<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__, 2) . '/Fixtures';
        putenv('POWERTOOLS_LOG_LEVEL');
        putenv('POWERTOOLS_SERVICE_NAME');
        putenv('Firstance_OBS_SAMPLE_RATE');
        putenv('Firstance_OBS_METRICS_NAMESPACE');
    }

    protected function tearDown(): void
    {
        putenv('POWERTOOLS_LOG_LEVEL');
        putenv('POWERTOOLS_SERVICE_NAME');
        putenv('Firstance_OBS_SAMPLE_RATE');
        putenv('Firstance_OBS_METRICS_NAMESPACE');
    }

    public function testLoadsValidFullConfig(): void
    {
        $config = ConfigLoader::load($this->fixturesPath . '/config.valid.yaml');

        $this->assertSame('test-service', $config->serviceName);
        $this->assertSame('2.0.0', $config->serviceVersion);
        $this->assertSame('DEBUG', $config->logLevel);
        $this->assertSame(0.5, $config->logSampleRate);
        $this->assertSame(['team' => 'test-team'], $config->persistentKeys);
        $this->assertFalse($config->tracerCaptureHTTPS);
        $this->assertSame('TestNS', $config->metricsNamespace);
    }

    public function testLoadsMinimalConfigWithDefaults(): void
    {
        $config = ConfigLoader::load($this->fixturesPath . '/config.minimal.yaml');

        $this->assertSame('minimal-service', $config->serviceName);
        $this->assertSame('0.0.0', $config->serviceVersion);
        $this->assertSame('INFO', $config->logLevel);
    }

    public function testThrowsOnInvalidConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigLoader::load($this->fixturesPath . '/config.invalid.yaml');
    }

    public function testCreatesDefaultConfigWhenYamlMissing(): void
    {
        $tempPath = $this->fixturesPath . '/config.auto-created.yaml';
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        try {
            $config = ConfigLoader::load($tempPath);
            $this->assertSame('poc-logger', $config->serviceName);
            $this->assertSame('0.2.1', $config->serviceVersion);
            $this->assertSame('INFO', $config->logLevel);
            $this->assertSame(1.0, $config->logSampleRate);
            $this->assertTrue($config->tracerEnabled);
            $this->assertSame('Default', $config->metricsNamespace);
            $this->assertFileExists($tempPath);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function testUsesProjectNameWhenServiceNameMissing(): void
    {
        $config = ConfigLoader::load($this->fixturesPath . '/config.no-service-name.yaml');
        $this->assertSame('poc-logger', $config->serviceName);
    }

    public function testWarnsWhenDefaultConfigCannotBeWritten(): void
    {
        $warningMessage = null;
        set_error_handler(function (int $errno, string $errstr) use (&$warningMessage): bool {
            $warningMessage = $errstr;
            return true;
        }, E_USER_WARNING);

        try {
            $config = ConfigLoader::load('/nonexistent-dir/impossible/config.yaml');
        } finally {
            restore_error_handler();
        }

        $this->assertSame('poc-logger', $config->serviceName);
        $this->assertSame(
            '[firstance-obs] Cannot create default config at /nonexistent-dir/impossible/config.yaml, using in-memory defaults',
            $warningMessage,
        );
    }

    public function testEnvOverridesYamlValues(): void
    {
        putenv('POWERTOOLS_LOG_LEVEL=ERROR');
        putenv('POWERTOOLS_SERVICE_NAME=env-service');
        putenv('Firstance_OBS_SAMPLE_RATE=0.75');
        putenv('Firstance_OBS_METRICS_NAMESPACE=EnvNS');

        $config = ConfigLoader::load($this->fixturesPath . '/config.valid.yaml');

        $this->assertSame('env-service', $config->serviceName);
        $this->assertSame('ERROR', $config->logLevel);
        $this->assertSame(0.75, $config->logSampleRate);
        $this->assertSame('EnvNS', $config->metricsNamespace);
    }

    public function testEnvTakesPrecedenceOverYaml(): void
    {
        putenv('POWERTOOLS_LOG_LEVEL=WARN');

        $config = ConfigLoader::load($this->fixturesPath . '/config.valid.yaml');

        $this->assertSame('WARN', $config->logLevel);
        $this->assertSame('test-service', $config->serviceName);
    }
}
