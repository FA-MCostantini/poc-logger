<?php

declare(strict_types=1);

namespace Bper\LambdaObs\Tests\Unit\Logger;

use Bper\LambdaObs\Logger\Severity;
use PHPUnit\Framework\TestCase;

final class SeverityTest extends TestCase
{
    public function testDebugLevel(): void
    {
        $this->assertSame(Severity::DEBUG, Severity::fromMonologLevel(100));
    }

    public function testInfoLevel(): void
    {
        $this->assertSame(Severity::INFO, Severity::fromMonologLevel(200));
    }

    public function testWarningLevel(): void
    {
        $this->assertSame(Severity::WARN, Severity::fromMonologLevel(300));
    }

    public function testErrorLevel(): void
    {
        $this->assertSame(Severity::ERROR, Severity::fromMonologLevel(400));
    }

    public function testCriticalMapsToError(): void
    {
        $this->assertSame(Severity::ERROR, Severity::fromMonologLevel(500));
    }
}
