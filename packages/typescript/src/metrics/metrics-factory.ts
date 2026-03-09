import { Metrics } from '@aws-lambda-powertools/metrics';
import type { FirstanceConfig } from '../config/types.js';

interface MetricsOptions extends FirstanceConfig {
  readonly serviceName: string;
}

export function createMetrics(config: MetricsOptions): Metrics {
  return new Metrics({
    namespace: config.metrics.namespace,
    serviceName: config.serviceName,
  });
}
