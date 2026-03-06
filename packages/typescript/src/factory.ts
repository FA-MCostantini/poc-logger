import { Logger } from '@aws-lambda-powertools/logger';
import type { Tracer } from '@aws-lambda-powertools/tracer';
import type { Metrics } from '@aws-lambda-powertools/metrics';
import type { MiddlewareLikeObj } from '@aws-lambda-powertools/commons/types';
import { loadConfig } from './config/loader.js';
import { OTelLogFormatter } from './logger/otel-formatter.js';
import { createTracer } from './tracer/tracer-factory.js';
import { createMetrics } from './metrics/metrics-factory.js';
import { createMiddlewareChain } from './middleware/middy-chain.js';

export interface BperLoggerOptions {
  readonly configPath: string;
}

export interface BperObservability {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  middleware(options?: { logEvent?: boolean }): MiddlewareLikeObj;
}

export function createBperLogger(options: BperLoggerOptions): BperObservability {
  const config = loadConfig({ configPath: options.configPath });

  const formatter = new OTelLogFormatter({
    serviceVersion: config.service.version,
  });

  const logger = new Logger({
    serviceName: config.service.name,
    logLevel: config.logger.level,
    sampleRateValue: config.logger.sampleRate,
    persistentLogAttributes: config.logger.persistentKeys,
    logFormatter: formatter,
  });

  const tracer = createTracer(config);
  const metrics = createMetrics(config);

  return {
    logger,
    tracer,
    metrics,
    middleware(mwOptions) {
      return createMiddlewareChain({
        logger,
        tracer,
        metrics,
        captureColdStart: config.metrics.captureColdStart,
        logEvent: mwOptions?.logEvent,
      });
    },
  };
}
