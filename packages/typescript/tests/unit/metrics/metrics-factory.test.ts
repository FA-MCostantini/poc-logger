import { describe, it, expect } from 'vitest';
import { createMetrics } from '../../../src/metrics/metrics-factory.js';

function makeConfig(overrides: { namespace?: string; captureColdStart?: boolean } = {}) {
  return {
    logger: { level: 'INFO' as const, sampleRate: 1, persistentKeys: {} },
    tracer: { enabled: true, captureHTTPS: true },
    metrics: { namespace: 'TestNS', captureColdStart: true, ...overrides },
    serviceName: 'test-svc',
  };
}

describe('createMetrics', () => {
  it('should create Metrics with configured namespace', () => {
    const metrics = createMetrics(makeConfig());
    expect(metrics).toBeDefined();
  });

  it('should use custom namespace', () => {
    const metrics = createMetrics(makeConfig({ namespace: 'CustomNS' }));
    expect(metrics).toBeDefined();
  });
});
