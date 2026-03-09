import { describe, it, expect } from 'vitest';
import { SDK_NAME, SDK_VERSION } from '../../src/version.js';

describe('version constants', () => {
  it('should export SDK_NAME as poc-logger', () => {
    expect(SDK_NAME).toBe('poc-logger');
  });

  it('should export SDK_VERSION as a string', () => {
    // In ESM (vitest), SDK_VERSION is the raw placeholder '__SDK_VERSION__'
    // In CJS (esbuild build), it's replaced with the actual version
    expect(typeof SDK_VERSION).toBe('string');
    expect(SDK_VERSION.length).toBeGreaterThan(0);
  });
});
