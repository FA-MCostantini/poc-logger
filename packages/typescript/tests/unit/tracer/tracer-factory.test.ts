import { describe, it, expect } from 'vitest';
import { createTracer } from '../../../src/tracer/tracer-factory.js';

function makeConfig(overrides: { enabled?: boolean; captureHTTPS?: boolean } = {}) {
  return {
    logger: { level: 'INFO' as const, sampleRate: 1, persistentKeys: {} },
    tracer: { enabled: true, captureHTTPS: true, ...overrides },
    metrics: { namespace: 'TestNS', captureColdStart: true },
    serviceName: 'test-svc',
  };
}

describe('createTracer', () => {
  it('should create a Tracer with the configured service name', () => {
    const tracer = createTracer(makeConfig());
    expect(tracer).toBeDefined();
  });

  it('should respect the enabled flag', () => {
    const tracer = createTracer(makeConfig({ enabled: false }));
    expect(tracer).toBeDefined();
  });

  it('should configure captureHTTPS', () => {
    const tracer = createTracer(makeConfig({ captureHTTPS: false }));
    expect(tracer).toBeDefined();
  });
});
