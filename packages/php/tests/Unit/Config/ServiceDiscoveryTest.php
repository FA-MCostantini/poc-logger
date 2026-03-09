<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ServiceDiscovery;
use PHPUnit\Framework\TestCase;

final class ServiceDiscoveryTest extends TestCase
{
    public function testServiceNameReturnsRootPackageName(): void
    {
        $name = ServiceDiscovery::serviceName();
        // Root package is firstance/poc-logger → strips vendor prefix
        $this->assertSame('poc-logger', $name);
    }

    public function testServiceNameDoesNotContainVendorPrefix(): void
    {
        $name = ServiceDiscovery::serviceName();
        $this->assertStringNotContainsString('/', $name);
    }

    public function testServiceVersionReturnsNonEmptyString(): void
    {
        $version = ServiceDiscovery::serviceVersion();
        $this->assertNotEmpty($version);
    }

    public function testSdkNameReturnsPocLogger(): void
    {
        $this->assertSame('poc-logger', ServiceDiscovery::sdkName());
    }

    public function testSdkVersionReturnsString(): void
    {
        $version = ServiceDiscovery::sdkVersion();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }
}
