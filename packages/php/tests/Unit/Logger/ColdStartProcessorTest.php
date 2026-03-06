<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Logger;

use Firstance\LambdaObs\Logger\ColdStartProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class ColdStartProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        ColdStartProcessor::reset();
    }

    public function testFirstInvocationIsColdStart(): void
    {
        $processor = new ColdStartProcessor();
        $record = $processor($this->makeRecord());
        $this->assertTrue($record->extra['cold_start']);
    }

    public function testSecondInvocationIsNotColdStart(): void
    {
        $processor = new ColdStartProcessor();
        $processor($this->makeRecord());
        $record = $processor($this->makeRecord());
        $this->assertFalse($record->extra['cold_start']);
    }

    public function testResetAllowsNewColdStart(): void
    {
        $processor = new ColdStartProcessor();
        $processor($this->makeRecord());
        ColdStartProcessor::reset();
        $record = $processor($this->makeRecord());
        $this->assertTrue($record->extra['cold_start']);
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
