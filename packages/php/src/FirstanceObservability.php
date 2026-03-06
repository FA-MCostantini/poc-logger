<?php

declare(strict_types=1);

namespace Firstance\LambdaObs;

use Firstance\LambdaObs\Metrics\EmfMetricsEmitter;
use Firstance\LambdaObs\Tracer\XRayTracerFactory;
use Monolog\Logger;

final readonly class FirstanceObservability
{
    public function __construct(
        public Logger $logger,
        public XRayTracerFactory $tracer,
        public EmfMetricsEmitter $metrics,
    ) {}
}
