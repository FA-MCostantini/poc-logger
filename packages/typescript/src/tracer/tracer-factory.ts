import { Tracer } from '@aws-lambda-powertools/tracer';
import type { FirstanceConfig } from '../config/types.js';

interface TracerOptions extends FirstanceConfig {
  readonly serviceName: string;
}

export function createTracer(config: TracerOptions): Tracer {
  return new Tracer({
    serviceName: config.serviceName,
    enabled: config.tracer.enabled,
    captureHTTPsRequests: config.tracer.captureHTTPS,
  });
}
