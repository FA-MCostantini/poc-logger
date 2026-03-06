<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tracer;

use Firstance\LambdaObs\Config\ConfigDTO;

final class XRayTracerFactory
{
    private readonly ConfigDTO $config;

    public function __construct(ConfigDTO $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return $this->config->tracerEnabled;
    }

    public function getServiceName(): string
    {
        return $this->config->serviceName;
    }

    public function getServiceVersion(): string
    {
        return $this->config->serviceVersion;
    }

    /**
     * Get the current X-Ray trace ID from the Lambda runtime environment.
     */
    public function getTraceId(): ?string
    {
        $traceHeader = getenv('_X_AMZN_TRACE_ID');

        return $traceHeader !== false ? $traceHeader : null;
    }
}
