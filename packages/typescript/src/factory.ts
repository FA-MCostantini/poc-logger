import { Logger } from '@aws-lambda-powertools/logger';
import type { Tracer } from '@aws-lambda-powertools/tracer';
import type { Metrics } from '@aws-lambda-powertools/metrics';
import type { MiddlewareObj } from '@middy/core';
import { loadConfig } from './config/loader.js';
import { OTelLogFormatter } from './logger/otel-formatter.js';
import { createTracer } from './tracer/tracer-factory.js';
import { createMetrics } from './metrics/metrics-factory.js';
import { createMiddlewareChain } from './middleware/middy-chain.js';
import { discoverService } from './service-discovery.js';
import { SDK_NAME, SDK_VERSION } from './version.js';

export interface FirstanceLoggerOptions {
  readonly configPath: string;
}

export interface FirstanceObservability {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  middleware(options?: { logEvent?: boolean }): MiddlewareObj;
}

export function createFirstanceLogger(options: FirstanceLoggerOptions): FirstanceObservability {
  const config = loadConfig({ configPath: options.configPath });
  const service = discoverService();

  const formatter = new OTelLogFormatter({
    serviceName: service.name,
    serviceVersion: service.version,
    sdkName: SDK_NAME,
    sdkVersion: SDK_VERSION,
  });

  const logger = new Logger({
    serviceName: service.name,
    logLevel: config.logger.level,
    sampleRateValue: config.logger.sampleRate,
    persistentLogAttributes: config.logger.persistentKeys,
    logFormatter: formatter,
  });

  const tracer = createTracer({ ...config, serviceName: service.name });
  const metrics = createMetrics({ ...config, serviceName: service.name });

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
