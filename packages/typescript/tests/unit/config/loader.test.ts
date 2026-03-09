import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import path from 'node:path';
import { existsSync, unlinkSync } from 'node:fs';
import { loadConfig } from '../../../src/config/loader.js';

const FIXTURES = path.resolve(__dirname, '../../fixtures');

describe('loadConfig', () => {
  const originalEnv = { ...process.env };

  beforeEach(() => {
    process.env = { ...originalEnv };
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  it('should load a valid full config from YAML', () => {
    const config = loadConfig({ configPath: path.join(FIXTURES, 'config.valid.yaml') });
    expect(config.service.name).toBe('test-service');
    expect(config.service.version).toBe('2.0.0');
    expect(config.logger.level).toBe('DEBUG');
    expect(config.logger.sampleRate).toBe(0.5);
    expect(config.logger.persistentKeys).toEqual({ team: 'test-team' });
    expect(config.tracer.captureHTTPS).toBe(false);
    expect(config.metrics.namespace).toBe('TestNS');
  });

  it('should load a minimal config and apply defaults', () => {
    const config = loadConfig({ configPath: path.join(FIXTURES, 'config.minimal.yaml') });
    expect(config.service.name).toBe('minimal-service');
    expect(config.service.version).toBe('0.0.0');
    expect(config.logger.level).toBe('INFO');
  });

  it('should throw on invalid YAML config', () => {
    expect(() =>
      loadConfig({ configPath: path.join(FIXTURES, 'config.invalid.yaml') })
    ).toThrow();
  });

  it('should create default config when YAML is missing', () => {
    const tempPath = path.join(FIXTURES, 'config.auto-created.yaml');
    // Ensure the file does not exist before the test
    if (existsSync(tempPath)) unlinkSync(tempPath);

    try {
      const config = loadConfig({ configPath: tempPath });
      expect(config.service.name).toBe('poc-logger');
      expect(config.service.version).toBe('0.0.0');
      expect(config.logger.level).toBe('INFO');
      expect(config.logger.sampleRate).toBe(1.0);
      expect(config.tracer.enabled).toBe(true);
      expect(config.metrics.namespace).toBe('Default');
      expect(existsSync(tempPath)).toBe(true);
    } finally {
      if (existsSync(tempPath)) unlinkSync(tempPath);
    }
  });

  it('should use service name from package.json when YAML has no service.name', () => {
    const config = loadConfig({ configPath: path.join(FIXTURES, 'config.no-service-name.yaml') });
    expect(config.service.name).toBe('poc-logger');
  });

  it('should warn but not throw when default config cannot be written', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    try {
      const config = loadConfig({ configPath: '/nonexistent-dir/impossible-path/config.yaml' });
      expect(config.service.name).toBe('poc-logger');
      expect(warnSpy).toHaveBeenCalledWith(
        expect.stringContaining('Cannot create default config'),
      );
    } finally {
      warnSpy.mockRestore();
    }
  });

  it('should override config with environment variables', () => {
    process.env['POWERTOOLS_LOG_LEVEL'] = 'ERROR';
    process.env['POWERTOOLS_SERVICE_NAME'] = 'env-service';
    process.env['Firstance_OBS_SAMPLE_RATE'] = '0.75';
    process.env['Firstance_OBS_METRICS_NAMESPACE'] = 'EnvNS';

    const config = loadConfig({ configPath: path.join(FIXTURES, 'config.valid.yaml') });
    expect(config.service.name).toBe('env-service');
    expect(config.logger.level).toBe('ERROR');
    expect(config.logger.sampleRate).toBe(0.75);
    expect(config.metrics.namespace).toBe('EnvNS');
  });

  it('should give env vars precedence over YAML values', () => {
    process.env['POWERTOOLS_LOG_LEVEL'] = 'WARN';

    const config = loadConfig({ configPath: path.join(FIXTURES, 'config.valid.yaml') });
    expect(config.logger.level).toBe('WARN');
    expect(config.service.name).toBe('test-service');
  });
});
