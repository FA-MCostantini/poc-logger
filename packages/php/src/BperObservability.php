<?php

declare(strict_types=1);

namespace Bper\LambdaObs;

use Bper\LambdaObs\Metrics\EmfMetricsEmitter;
use Bper\LambdaObs\Tracer\XRayTracerFactory;
use Monolog\Logger;

final readonly class BperObservability
{
    public function __construct(
        public Logger $logger,
        public XRayTracerFactory $tracer,
        public EmfMetricsEmitter $metrics,
    ) {}
}
