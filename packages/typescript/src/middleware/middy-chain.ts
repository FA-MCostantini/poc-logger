import { injectLambdaContext } from '@aws-lambda-powertools/logger/middleware';
import { captureLambdaHandler } from '@aws-lambda-powertools/tracer/middleware';
import { logMetrics } from '@aws-lambda-powertools/metrics/middleware';
import type { MiddlewareLikeObj } from '@aws-lambda-powertools/commons/types';
import type { Logger } from '@aws-lambda-powertools/logger';
import type { Tracer } from '@aws-lambda-powertools/tracer';
import type { Metrics } from '@aws-lambda-powertools/metrics';

export interface MiddlewareChainOptions {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  readonly captureColdStart: boolean;
  readonly logEvent?: boolean;
}

/**
 * Composes Powertools Logger, Tracer, and Metrics middleware into a single
 * Middy-compatible MiddlewareObj.
 *
 * Execution order (before): Tracer → Logger → Metrics
 * Execution order (after):  Metrics → Logger → Tracer  (reverse)
 */
export function createMiddlewareChain(
  options: MiddlewareChainOptions
): MiddlewareLikeObj {
  const tracerMw = captureLambdaHandler(options.tracer);
  const loggerMw = injectLambdaContext(options.logger, {
    logEvent: options.logEvent ?? false,
  });
  const metricsMw = logMetrics(options.metrics, {
    captureColdStartMetric: options.captureColdStart,
  });

  return {
    before: async (request) => {
      await tracerMw.before?.(request);
      await loggerMw.before?.(request);
      await metricsMw.before?.(request);
    },
    after: async (request) => {
      await metricsMw.after?.(request);
      await loggerMw.after?.(request);
      await tracerMw.after?.(request);
    },
    onError: async (request) => {
      await tracerMw.onError?.(request);
    },
  };
}
