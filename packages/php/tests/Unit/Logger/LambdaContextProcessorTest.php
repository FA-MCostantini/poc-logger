<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Logger;

use Firstance\LambdaObs\Logger\LambdaContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class LambdaContextProcessorTest extends TestCase
{
    private LambdaContextProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new LambdaContextProcessor();
    }

    protected function tearDown(): void
    {
        putenv('AWS_LAMBDA_FUNCTION_NAME');
        putenv('AWS_LAMBDA_FUNCTION_VERSION');
        putenv('AWS_REGION');
        putenv('_X_AMZN_REQUEST_ID');
        putenv('_X_AMZN_TRACE_ID');
    }

    public function testAddsFunctionNameFromEnv(): void
    {
        putenv('AWS_LAMBDA_FUNCTION_NAME=my-lambda');
        $record = ($this->processor)($this->makeRecord());
        $this->assertSame('my-lambda', $record->extra['faas.name']);
    }

    public function testAddsRegionFromEnv(): void
    {
        putenv('AWS_REGION=eu-south-1');
        $record = ($this->processor)($this->makeRecord());
        $this->assertSame('eu-south-1', $record->extra['cloud.region']);
    }

    public function testAddsRequestIdFromEnv(): void
    {
        putenv('_X_AMZN_REQUEST_ID=req-123');
        $record = ($this->processor)($this->makeRecord());
        $this->assertSame('req-123', $record->extra['aws_request_id']);
    }

    public function testAddsTraceIdFromEnv(): void
    {
        putenv('_X_AMZN_TRACE_ID=1-abc-def');
        $record = ($this->processor)($this->makeRecord());
        $this->assertSame('1-abc-def', $record->extra['trace_id']);
    }

    public function testDefaultsToEmptyWhenEnvNotSet(): void
    {
        $record = ($this->processor)($this->makeRecord());
        $this->assertSame('', $record->extra['faas.name']);
        $this->assertSame('', $record->extra['cloud.region']);
        $this->assertSame('', $record->extra['aws_request_id']);
        $this->assertNull($record->extra['trace_id']);
    }

    private function makeRecord(): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
        );
    }
}
