import { describe, it, expect } from 'vitest';
import { createMiddlewareChain } from '../../../src/middleware/middy-chain.js';
import { Logger } from '@aws-lambda-powertools/logger';
import { Tracer } from '@aws-lambda-powertools/tracer';
import { Metrics } from '@aws-lambda-powertools/metrics';

describe('createMiddlewareChain', () => {
  it('should return a middy MiddlewareObj with before hook', () => {
    const logger = new Logger({ serviceName: 'test' });
    const tracer = new Tracer({ serviceName: 'test', enabled: false });
    const metrics = new Metrics({ namespace: 'Test', serviceName: 'test' });

    const chain = createMiddlewareChain({ logger, tracer, metrics, captureColdStart: true });

    expect(chain).toBeDefined();
    expect(chain.before).toBeDefined();
    expect(typeof chain.before).toBe('function');
  });

  it('should return a middy MiddlewareObj with after hook', () => {
    const logger = new Logger({ serviceName: 'test' });
    const tracer = new Tracer({ serviceName: 'test', enabled: false });
    const metrics = new Metrics({ namespace: 'Test', serviceName: 'test' });

    const chain = createMiddlewareChain({ logger, tracer, metrics, captureColdStart: true });

    expect(chain.after).toBeDefined();
    expect(typeof chain.after).toBe('function');
  });

  it('should accept logEvent option', () => {
    const logger = new Logger({ serviceName: 'test' });
    const tracer = new Tracer({ serviceName: 'test', enabled: false });
    const metrics = new Metrics({ namespace: 'Test', serviceName: 'test' });

    const chain = createMiddlewareChain({
      logger, tracer, metrics,
      captureColdStart: true,
      logEvent: true,
    });

    expect(chain).toBeDefined();
  });
});
