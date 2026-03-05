<?php

declare(strict_types=1);

namespace Bper\LambdaObs\Config;

final readonly class ConfigDTO
{
    /**
     * @param array<string, string> $persistentKeys
     */
    public function __construct(
        public string $serviceName,
        public string $serviceVersion = '0.0.0',
        public string $logLevel = 'INFO',
        public float $logSampleRate = 1.0,
        public array $persistentKeys = [],
        public bool $tracerEnabled = true,
        public bool $tracerCaptureHTTPS = true,
        public string $metricsNamespace = 'Default',
        public bool $metricsCaptureColdStart = true,
    ) {}
}
