import { Tracer } from '@aws-lambda-powertools/tracer';
import type { FirstanceConfig } from '../config/types.js';

export function createTracer(config: FirstanceConfig): Tracer {
  return new Tracer({
    serviceName: config.service.name,
    enabled: config.tracer.enabled,
    captureHTTPsRequests: config.tracer.captureHTTPS,
  });
}
