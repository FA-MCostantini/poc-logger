import { Metrics } from '@aws-lambda-powertools/metrics';
import type { FirstanceConfig } from '../config/types.js';

export function createMetrics(config: FirstanceConfig): Metrics {
  return new Metrics({
    namespace: config.metrics.namespace,
    serviceName: config.service.name,
  });
}
