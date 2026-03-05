import { describe, it, expect } from 'vitest';
import { configSchema } from '../../../src/config/schema.js';

describe('configSchema', () => {
  it('should parse a valid full config', () => {
    const input = {
      service: { name: 'my-service', version: '1.0.0' },
      logger: { level: 'INFO', sampleRate: 0.5, persistentKeys: { team: 'a' } },
      tracer: { enabled: true, captureHTTPS: false },
      metrics: { namespace: 'MyNS', captureColdStart: true },
    };
    const result = configSchema.parse(input);
    expect(result.service.name).toBe('my-service');
    expect(result.logger.level).toBe('INFO');
    expect(result.tracer.captureHTTPS).toBe(false);
  });

  it('should apply defaults for optional sections', () => {
    const input = { service: { name: 'minimal' } };
    const result = configSchema.parse(input);
    expect(result.service.version).toBe('0.0.0');
    expect(result.logger.level).toBe('INFO');
    expect(result.logger.sampleRate).toBe(1.0);
    expect(result.logger.persistentKeys).toEqual({});
    expect(result.tracer.enabled).toBe(true);
    expect(result.metrics.namespace).toBe('Default');
  });

  it('should reject config without service.name', () => {
    expect(() => configSchema.parse({})).toThrow();
    expect(() => configSchema.parse({ service: {} })).toThrow();
  });

  it('should reject invalid log level', () => {
    const input = { service: { name: 's' }, logger: { level: 'TRACE' } };
    expect(() => configSchema.parse(input)).toThrow();
  });

  it('should reject sampleRate out of range', () => {
    expect(() =>
      configSchema.parse({ service: { name: 's' }, logger: { sampleRate: 1.5 } })
    ).toThrow();
    expect(() =>
      configSchema.parse({ service: { name: 's' }, logger: { sampleRate: -0.1 } })
    ).toThrow();
  });
});
