<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

use Composer\InstalledVersions;

final class ServiceDiscovery
{
    public static function serviceName(): string
    {
        $root = InstalledVersions::getRootPackage();
        $name = $root['name'];
        if ($name !== '' && $name !== '__root__') {
            $parts = explode('/', $name);
            return end($parts) ?: 'unknown';
        }

        return 'unknown';
    }

    public static function serviceVersion(): string
    {
        $root = InstalledVersions::getRootPackage();
        $version = $root['pretty_version'];
        if ($version !== '' && !str_starts_with($version, 'dev-') && !str_contains($version, 'no-version-set')) {
            return $version;
        }

        return '0.0.0';
    }

    public static function sdkName(): string
    {
        return 'poc-logger';
    }

    public static function sdkVersion(): string
    {
        try {
            $version = InstalledVersions::getVersion('firstance/poc-logger');
            return $version ?? '0.0.0';
        } catch (\OutOfBoundsException) {
            return '0.0.0';
        }
    }
}
