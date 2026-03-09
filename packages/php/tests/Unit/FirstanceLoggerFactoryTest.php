<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit;

use Firstance\LambdaObs\FirstanceLoggerFactory;
use Firstance\LambdaObs\FirstanceObservability;
use Firstance\LambdaObs\Config\ConfigDTO;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class FirstanceLoggerFactoryTest extends TestCase
{
    public function testCreateFromConfigReturnsObservability(): void
    {
        $config = new ConfigDTO();
        $obs = FirstanceLoggerFactory::createFromConfig($config);

        $this->assertInstanceOf(FirstanceObservability::class, $obs);
    }

    public function testLoggerIsMonologInstance(): void
    {
        $config = new ConfigDTO();
        $obs = FirstanceLoggerFactory::createFromConfig($config);

        $this->assertInstanceOf(Logger::class, $obs->logger);
    }

    public function testTracerIsConfigured(): void
    {
        $config = new ConfigDTO(tracerEnabled: true);
        $obs = FirstanceLoggerFactory::createFromConfig($config);

        $this->assertTrue($obs->tracer->isEnabled());
    }

    public function testCreateFromConfigFileWorks(): void
    {
        $configPath = __DIR__ . '/../Fixtures/config.valid.yaml';
        $obs = FirstanceLoggerFactory::create($configPath);

        $this->assertInstanceOf(FirstanceObservability::class, $obs);
    }
}
