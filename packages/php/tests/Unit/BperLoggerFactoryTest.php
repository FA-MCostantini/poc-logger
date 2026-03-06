<?php

declare(strict_types=1);

namespace Bper\LambdaObs\Tests\Unit;

use Bper\LambdaObs\BperLoggerFactory;
use Bper\LambdaObs\BperObservability;
use Bper\LambdaObs\Config\ConfigDTO;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class BperLoggerFactoryTest extends TestCase
{
    public function testCreateFromConfigReturnsObservability(): void
    {
        $config = new ConfigDTO(serviceName: 'test-svc', serviceVersion: '1.0.0');
        $obs = BperLoggerFactory::createFromConfig($config);

        $this->assertInstanceOf(BperObservability::class, $obs);
    }

    public function testLoggerIsMonologInstance(): void
    {
        $config = new ConfigDTO(serviceName: 'test-svc');
        $obs = BperLoggerFactory::createFromConfig($config);

        $this->assertInstanceOf(Logger::class, $obs->logger);
        $this->assertSame('test-svc', $obs->logger->getName());
    }

    public function testTracerIsConfigured(): void
    {
        $config = new ConfigDTO(serviceName: 'test-svc', tracerEnabled: true);
        $obs = BperLoggerFactory::createFromConfig($config);

        $this->assertTrue($obs->tracer->isEnabled());
        $this->assertSame('test-svc', $obs->tracer->getServiceName());
    }

    public function testCreateFromConfigFileWorks(): void
    {
        $configPath = __DIR__ . '/../Fixtures/config.valid.yaml';
        $obs = BperLoggerFactory::create($configPath);

        $this->assertInstanceOf(BperObservability::class, $obs);
        $this->assertSame('test-service', $obs->logger->getName());
    }
}
