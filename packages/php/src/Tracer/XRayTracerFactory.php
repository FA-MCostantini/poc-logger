<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Tracer;

use Firstance\LambdaObs\Config\ConfigDTO;

final class XRayTracerFactory
{
    private readonly ConfigDTO $config;
    private readonly string $serviceName;

    public function __construct(ConfigDTO $config, string $serviceName)
    {
        $this->config = $config;
        $this->serviceName = $serviceName;
    }

    public function isEnabled(): bool
    {
        return $this->config->tracerEnabled;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
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
