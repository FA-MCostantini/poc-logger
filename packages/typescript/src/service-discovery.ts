import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';

export interface ServiceInfo {
  readonly name: string;
  readonly version: string;
}

export function discoverService(): ServiceInfo {
  let dir = process.cwd();
  for (;;) {
    const pkgPath = resolve(dir, 'package.json');
    if (existsSync(pkgPath)) {
      try {
        const pkg = JSON.parse(readFileSync(pkgPath, 'utf-8')) as Record<string, unknown>;
        const name = typeof pkg['name'] === 'string' && pkg['name'] !== ''
          ? (pkg['name'] as string).replace(/^@[^/]+\//, '')
          : 'unknown';
        const version = typeof pkg['version'] === 'string' && pkg['version'] !== ''
          ? pkg['version'] as string
          : '0.0.0';
        return { name, version };
      } catch { /* ignore unreadable package.json */ }
    }
    const parent = dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }
  return { name: 'unknown', version: '0.0.0' };
}
