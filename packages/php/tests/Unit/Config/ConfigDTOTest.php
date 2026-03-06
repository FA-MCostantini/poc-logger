<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ConfigDTO;
use PHPUnit\Framework\TestCase;

final class ConfigDTOTest extends TestCase
{
    public function testCreatesWithAllValues(): void
    {
        $dto = new ConfigDTO(
            serviceName: 'my-service',
            serviceVersion: '1.0.0',
            logLevel: 'DEBUG',
            logSampleRate: 0.5,
            persistentKeys: ['team' => 'integrations'],
            tracerEnabled: true,
            tracerCaptureHTTPS: false,
            metricsNamespace: 'MyNS',
            metricsCaptureColdStart: true,
        );

        $this->assertSame('my-service', $dto->serviceName);
        $this->assertSame('1.0.0', $dto->serviceVersion);
        $this->assertSame('DEBUG', $dto->logLevel);
        $this->assertSame(0.5, $dto->logSampleRate);
        $this->assertSame(['team' => 'integrations'], $dto->persistentKeys);
        $this->assertTrue($dto->tracerEnabled);
        $this->assertFalse($dto->tracerCaptureHTTPS);
        $this->assertSame('MyNS', $dto->metricsNamespace);
        $this->assertTrue($dto->metricsCaptureColdStart);
    }

    public function testCreatesWithDefaults(): void
    {
        $dto = new ConfigDTO(serviceName: 'minimal');

        $this->assertSame('minimal', $dto->serviceName);
        $this->assertSame('0.0.0', $dto->serviceVersion);
        $this->assertSame('INFO', $dto->logLevel);
        $this->assertSame(1.0, $dto->logSampleRate);
        $this->assertSame([], $dto->persistentKeys);
        $this->assertTrue($dto->tracerEnabled);
        $this->assertTrue($dto->tracerCaptureHTTPS);
        $this->assertSame('Default', $dto->metricsNamespace);
        $this->assertTrue($dto->metricsCaptureColdStart);
    }
}
