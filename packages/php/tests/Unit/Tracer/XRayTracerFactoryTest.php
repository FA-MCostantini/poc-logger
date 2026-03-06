<?php

declare(strict_types=1);

namespace Bper\LambdaObs\Tests\Unit\Tracer;

use Bper\LambdaObs\Config\ConfigDTO;
use Bper\LambdaObs\Tracer\XRayTracerFactory;
use PHPUnit\Framework\TestCase;

final class XRayTracerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('_X_AMZN_TRACE_ID');
    }

    public function testReportsEnabledFromConfig(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(serviceName: 'test', tracerEnabled: true));
        $this->assertTrue($factory->isEnabled());
    }

    public function testReportsDisabledFromConfig(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(serviceName: 'test', tracerEnabled: false));
        $this->assertFalse($factory->isEnabled());
    }

    public function testReturnsServiceName(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(serviceName: 'my-svc'));
        $this->assertSame('my-svc', $factory->getServiceName());
    }

    public function testReturnsTraceIdFromEnv(): void
    {
        putenv('_X_AMZN_TRACE_ID=1-abc-def');
        $factory = new XRayTracerFactory(new ConfigDTO(serviceName: 'test'));
        $this->assertSame('1-abc-def', $factory->getTraceId());
    }

    public function testReturnsNullTraceIdWhenNotSet(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(serviceName: 'test'));
        $this->assertNull($factory->getTraceId());
    }
}
