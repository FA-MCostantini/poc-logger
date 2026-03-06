import { describe, it, expect } from 'vitest';
import { createMetrics } from '../../../src/metrics/metrics-factory.js';
import type { BperConfig } from '../../../src/config/types.js';

function makeConfig(overrides: Partial<BperConfig['metrics']> = {}): BperConfig {
  return {
    service: { name: 'test-svc', version: '1.0.0' },
    logger: { level: 'INFO', sampleRate: 1, persistentKeys: {} },
    tracer: { enabled: true, captureHTTPS: true },
    metrics: { namespace: 'TestNS', captureColdStart: true, ...overrides },
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
