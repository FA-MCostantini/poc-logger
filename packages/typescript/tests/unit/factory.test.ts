import { describe, it, expect } from 'vitest';
import { createFirstanceLogger } from '../../src/factory.js';
import { resolve } from 'node:path';

const fixturesDir = resolve(__dirname, '../fixtures');

describe('createFirstanceLogger', () => {
  it('should create FirstanceObservability from config file', () => {
    const result = createFirstanceLogger({
      configPath: resolve(fixturesDir, 'config.valid.yaml'),
    });

    expect(result.logger).toBeDefined();
    expect(result.tracer).toBeDefined();
    expect(result.metrics).toBeDefined();
    expect(typeof result.middleware).toBe('function');
  });

  it('should return middleware as a MiddlewareLikeObj', () => {
    const result = createFirstanceLogger({
      configPath: resolve(fixturesDir, 'config.valid.yaml'),
    });

    const mw = result.middleware();
    expect(mw.before).toBeDefined();
    expect(mw.after).toBeDefined();
  });
});
