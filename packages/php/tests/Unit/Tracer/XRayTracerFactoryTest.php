<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Tracer;

use Firstance\LambdaObs\Config\ConfigDTO;
use Firstance\LambdaObs\Tracer\XRayTracerFactory;
use PHPUnit\Framework\TestCase;

final class XRayTracerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('_X_AMZN_TRACE_ID');
    }

    public function testReportsEnabledFromConfig(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(tracerEnabled: true), 'test');
        $this->assertTrue($factory->isEnabled());
    }

    public function testReportsDisabledFromConfig(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(tracerEnabled: false), 'test');
        $this->assertFalse($factory->isEnabled());
    }

    public function testReturnsServiceName(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(), 'my-svc');
        $this->assertSame('my-svc', $factory->getServiceName());
    }

    public function testReturnsTraceIdFromEnv(): void
    {
        putenv('_X_AMZN_TRACE_ID=1-abc-def');
        $factory = new XRayTracerFactory(new ConfigDTO(), 'test');
        $this->assertSame('1-abc-def', $factory->getTraceId());
    }

    public function testReturnsNullTraceIdWhenNotSet(): void
    {
        $factory = new XRayTracerFactory(new ConfigDTO(), 'test');
        $this->assertNull($factory->getTraceId());
    }
}
