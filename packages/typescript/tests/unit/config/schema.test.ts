import { describe, it, expect } from 'vitest';
import { configSchema } from '../../../src/config/schema.js';

describe('configSchema', () => {
  it('should parse a valid full config', () => {
    const input = {
      logger: { level: 'INFO', sampleRate: 0.5, persistentKeys: { team: 'a' } },
      tracer: { enabled: true, captureHTTPS: false },
      metrics: { namespace: 'MyNS', captureColdStart: true },
    };
    const result = configSchema.parse(input);
    expect(result.logger.level).toBe('INFO');
    expect(result.tracer.captureHTTPS).toBe(false);
  });

  it('should apply defaults for optional sections', () => {
    const result = configSchema.parse({});
    expect(result.logger.level).toBe('INFO');
    expect(result.logger.sampleRate).toBe(1.0);
    expect(result.logger.persistentKeys).toEqual({});
    expect(result.tracer.enabled).toBe(true);
    expect(result.metrics.namespace).toBe('Default');
  });

  it('should reject invalid log level', () => {
    const input = { logger: { level: 'TRACE' } };
    expect(() => configSchema.parse(input)).toThrow();
  });

  it('should reject sampleRate out of range', () => {
    expect(() =>
      configSchema.parse({ logger: { sampleRate: 1.5 } })
    ).toThrow();
    expect(() =>
      configSchema.parse({ logger: { sampleRate: -0.1 } })
    ).toThrow();
  });
});
