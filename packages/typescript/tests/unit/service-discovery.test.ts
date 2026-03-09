import { describe, it, expect } from 'vitest';
import { discoverService } from '../../src/service-discovery.js';

describe('discoverService', () => {
  it('should discover service name and version from package.json', () => {
    const service = discoverService();
    // Running from the monorepo root, the root package.json has name "poc-logger"
    expect(service.name).toBe('poc-logger');
    expect(service.version).toMatch(/^\d+\.\d+\.\d+/);
  });

  it('should return name without scope prefix', () => {
    const service = discoverService();
    // poc-logger has no scope, but the function strips @scope/ prefixes
    expect(service.name).not.toContain('@');
    expect(service.name).not.toContain('/');
  });

  it('should return a non-empty version string', () => {
    const service = discoverService();
    expect(service.version).not.toBe('');
    expect(service.version).not.toBe('0.0.0');
  });
});
