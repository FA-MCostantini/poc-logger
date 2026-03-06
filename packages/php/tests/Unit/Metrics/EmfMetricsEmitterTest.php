<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Metrics;

use Firstance\LambdaObs\Config\ConfigDTO;
use Firstance\LambdaObs\Metrics\EmfMetricsEmitter;
use PHPUnit\Framework\TestCase;

final class EmfMetricsEmitterTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function testPutMetricWritesEmfJson(): void
    {
        $emitter = $this->createEmitter();
        $emitter->putMetric('RequestCount', 1.0);

        $output = $this->readStream();
        $this->assertNotEmpty($output);

        $decoded = json_decode($output, true);
        $this->assertArrayHasKey('_aws', $decoded);
        $this->assertSame(1.0, $decoded['RequestCount']);
    }

    public function testEmfStructureIncludesNamespace(): void
    {
        $emitter = $this->createEmitter();
        $emitter->putMetric('TestMetric', 42.0, 'Milliseconds');

        $decoded = json_decode($this->readStream(), true);
        $cwMetrics = $decoded['_aws']['CloudWatchMetrics'][0];

        $this->assertSame('TestNS', $cwMetrics['Namespace']);
        $this->assertSame('TestMetric', $cwMetrics['Metrics'][0]['Name']);
        $this->assertSame('Milliseconds', $cwMetrics['Metrics'][0]['Unit']);
    }

    public function testIncludesServiceDimension(): void
    {
        $emitter = $this->createEmitter();
        $emitter->putMetric('Test', 1.0);

        $decoded = json_decode($this->readStream(), true);
        $this->assertSame('test-svc', $decoded['service']);
    }

    public function testAcceptsCustomDimensions(): void
    {
        $emitter = $this->createEmitter();
        $emitter->putMetric('Test', 1.0, 'Count', ['env' => 'prod']);

        $decoded = json_decode($this->readStream(), true);
        $this->assertSame('prod', $decoded['env']);
        $this->assertContains('env', $decoded['_aws']['CloudWatchMetrics'][0]['Dimensions'][0]);
    }

    public function testEmitColdStartMetricWritesWhenEnabled(): void
    {
        $emitter = $this->createEmitter(captureColdStart: true);
        $emitter->emitColdStartMetric(true);

        $decoded = json_decode($this->readStream(), true);
        $this->assertSame(1.0, $decoded['ColdStart']);
    }

    public function testEmitColdStartMetricSkipsWhenDisabled(): void
    {
        $emitter = $this->createEmitter(captureColdStart: false);
        $emitter->emitColdStartMetric(true);

        $this->assertEmpty($this->readStream());
    }

    public function testTimestampIsInMilliseconds(): void
    {
        $emitter = $this->createEmitter();
        $emitter->putMetric('Test', 1.0);

        $decoded = json_decode($this->readStream(), true);
        $timestamp = $decoded['_aws']['Timestamp'];
        // Timestamp should be in milliseconds (13+ digits)
        $this->assertGreaterThan(1_000_000_000_000, $timestamp);
    }

    private function createEmitter(bool $captureColdStart = true): EmfMetricsEmitter
    {
        $config = new ConfigDTO(
            serviceName: 'test-svc',
            metricsNamespace: 'TestNS',
            metricsCaptureColdStart: $captureColdStart,
        );

        return new EmfMetricsEmitter($config, $this->stream);
    }

    private function readStream(): string
    {
        rewind($this->stream);
        return stream_get_contents($this->stream) ?: '';
    }
}
