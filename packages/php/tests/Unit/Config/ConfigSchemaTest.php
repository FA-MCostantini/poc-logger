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
            'service' => ['name' => 'test', 'version' => '1.0.0'],
            'logger' => ['level' => 'DEBUG', 'sampleRate' => 0.5, 'persistentKeys' => ['k' => 'v']],
            'tracer' => ['enabled' => true, 'captureHTTPS' => false],
            'metrics' => ['namespace' => 'NS', 'captureColdStart' => true],
        ];

        $dto = ConfigSchema::validate($raw);

        $this->assertSame('test', $dto->serviceName);
        $this->assertSame('DEBUG', $dto->logLevel);
        $this->assertFalse($dto->tracerCaptureHTTPS);
    }

    public function testAppliesDefaults(): void
    {
        $raw = ['service' => ['name' => 'minimal']];

        $dto = ConfigSchema::validate($raw);

        $this->assertSame('0.0.0', $dto->serviceVersion);
        $this->assertSame('INFO', $dto->logLevel);
        $this->assertSame(1.0, $dto->logSampleRate);
    }

    public function testThrowsOnMissingServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('service.name');

        ConfigSchema::validate([]);
    }

    public function testThrowsOnEmptyServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigSchema::validate(['service' => ['name' => '']]);
    }

    public function testThrowsOnInvalidLogLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.level');

        ConfigSchema::validate(['service' => ['name' => 's'], 'logger' => ['level' => 'TRACE']]);
    }

    public function testThrowsOnSampleRateOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigSchema::validate(['service' => ['name' => 's'], 'logger' => ['sampleRate' => 1.5]]);
    }
}
