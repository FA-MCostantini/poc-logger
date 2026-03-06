import { Metrics } from '@aws-lambda-powertools/metrics';
import type { BperConfig } from '../config/types.js';

export function createMetrics(config: BperConfig): Metrics {
  return new Metrics({
    namespace: config.metrics.namespace,
    serviceName: config.service.name,
  });
}
