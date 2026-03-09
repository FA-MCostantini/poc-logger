<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ConfigSchema;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaTest extends TestCase
{
    public function testValidatesFullConfig(): void
    {
        $raw = [
            'logger' => ['level' => 'DEBUG', 'sampleRate' => 0.5, 'persistentKeys' => ['k' => 'v']],
            'tracer' => ['enabled' => true, 'captureHTTPS' => false],
            'metrics' => ['namespace' => 'NS', 'captureColdStart' => true],
        ];

        $dto = ConfigSchema::validate($raw);

        $this->assertSame('DEBUG', $dto->logLevel);
        $this->assertFalse($dto->tracerCaptureHTTPS);
    }

    public function testAppliesDefaults(): void
    {
        $dto = ConfigSchema::validate([]);

        $this->assertSame('INFO', $dto->logLevel);
        $this->assertSame(1.0, $dto->logSampleRate);
    }

    public function testThrowsOnInvalidLogLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.level');

        ConfigSchema::validate(['logger' => ['level' => 'TRACE']]);
    }

    public function testThrowsOnSampleRateOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigSchema::validate(['logger' => ['sampleRate' => 1.5]]);
    }
}
