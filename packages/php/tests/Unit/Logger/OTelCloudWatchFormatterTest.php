<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Logger;

use Firstance\LambdaObs\Logger\OTelCloudWatchFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class OTelCloudWatchFormatterTest extends TestCase
{
    private OTelCloudWatchFormatter $formatter;

    protected function setUp(): void
    {
        putenv('AWS_LAMBDA_FUNCTION_VERSION=$LATEST');
        putenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE=512');
        putenv('AWS_LAMBDA_LOG_STREAM_NAME=2026/03/09/[$LATEST]abc123');

        $this->formatter = new OTelCloudWatchFormatter(
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            sdkName: 'poc-logger',
            sdkVersion: '0.2.3',
            region: 'eu-south-1',
        );
    }

    protected function tearDown(): void
    {
        putenv('AWS_LAMBDA_FUNCTION_VERSION');
        putenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE');
        putenv('AWS_LAMBDA_LOG_STREAM_NAME');
    }

    public function testProducesOTelCompliantStructure(): void
    {
        $record = $this->makeRecord('test message', Level::Info);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertArrayHasKey('Timestamp', $output);
        $this->assertSame('INFO', $output['SeverityText']);
        $this->assertSame(9, $output['SeverityNumber']);
        $this->assertSame('test message', $output['Body']);
    }

    public function testIncludesAllResourceFields(): void
    {
        $record = $this->makeRecord('test', Level::Info);
        $output = json_decode($this->formatter->format($record), true);

        $resource = $output['Resource'];
        $this->assertSame('test-service', $resource['service.name']);
        $this->assertSame('1.0.0', $resource['service.version']);
        $this->assertSame('poc-logger', $resource['telemetry.sdk.name']);
        $this->assertSame('0.2.3', $resource['telemetry.sdk.version']);
        $this->assertSame('php', $resource['service.language']);
        $this->assertSame('$LATEST', $resource['faas.version']);
        $this->assertSame('512', $resource['faas.memory']);
        $this->assertSame('2026/03/09/[$LATEST]abc123', $resource['faas.instance']);
        $this->assertSame('aws', $resource['cloud.provider']);
        $this->assertSame('eu-south-1', $resource['cloud.region']);
        $this->assertSame(PHP_VERSION, $resource['process.runtime.version']);
    }

    public function testMapsSeverityLevelsCorrectly(): void
    {
        $cases = [
            [Level::Debug, 'DEBUG', 5],
            [Level::Info, 'INFO', 9],
            [Level::Warning, 'WARN', 13],
            [Level::Error, 'ERROR', 17],
        ];

        foreach ($cases as [$level, $expectedText, $expectedNumber]) {
            $record = $this->makeRecord('test', $level);
            $output = json_decode($this->formatter->format($record), true);
            $this->assertSame($expectedText, $output['SeverityText'], "Failed for level {$level->name}");
            $this->assertSame($expectedNumber, $output['SeverityNumber'], "Failed for level {$level->name}");
        }
    }

    public function testIncludesTraceIdFromExtra(): void
    {
        $record = $this->makeRecord('test', Level::Info, extra: ['trace_id' => '1-abc-def']);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertSame('1-abc-def', $output['TraceId']);
    }

    public function testMergesContextIntoAttributes(): void
    {
        $record = $this->makeRecord('test', Level::Info, context: ['orderId' => 42]);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertSame(42, $output['Attributes']['orderId']);
    }

    public function testIncludesColdStartInAttributes(): void
    {
        $record = $this->makeRecord('test', Level::Info, extra: ['cold_start' => true]);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertTrue($output['Attributes']['cold_start']);
    }

    public function testIncludesFaasNameFromExtra(): void
    {
        $record = $this->makeRecord('test', Level::Info, extra: ['faas.name' => 'my-lambda']);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertSame('my-lambda', $output['Resource']['faas.name']);
    }

    public function testHandlesMissingLambdaContextGracefully(): void
    {
        $record = $this->makeRecord('test', Level::Info);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertSame('', $output['Resource']['faas.name']);
    }

    public function testOmitsTraceIdWhenNotPresent(): void
    {
        $record = $this->makeRecord('test', Level::Info);
        $output = json_decode($this->formatter->format($record), true);

        $this->assertArrayNotHasKey('TraceId', $output);
    }

    public function testDefaultsFaasFieldsWhenEnvNotSet(): void
    {
        putenv('AWS_LAMBDA_FUNCTION_VERSION');
        putenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE');
        putenv('AWS_LAMBDA_LOG_STREAM_NAME');

        $formatter = new OTelCloudWatchFormatter(
            serviceName: 'test',
            serviceVersion: '1.0.0',
            sdkName: 'poc-logger',
            sdkVersion: '0.2.3',
            region: 'eu-south-1',
        );

        $record = $this->makeRecord('test', Level::Info);
        $output = json_decode($formatter->format($record), true);

        $this->assertSame('', $output['Resource']['faas.version']);
        $this->assertSame('', $output['Resource']['faas.memory']);
        $this->assertSame('', $output['Resource']['faas.instance']);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function makeRecord(
        string $message,
        Level $level,
        array $context = [],
        array $extra = [],
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2026-03-06T10:00:00.000Z'),
            channel: 'test',
            level: $level,
            message: $message,
            context: $context,
            extra: $extra,
        );
    }
}
