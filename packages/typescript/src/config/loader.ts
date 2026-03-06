import { readFileSync } from 'node:fs';
import yaml from 'js-yaml';
import { configSchema } from './schema.js';
import type { FirstanceConfig } from './types.js';

interface LoadConfigOptions {
  readonly configPath: string;
}

export function loadConfig(options: LoadConfigOptions): FirstanceConfig {
  const raw = readYaml(options.configPath);
  const merged = applyEnvOverrides(raw);
  return configSchema.parse(merged);
}

function readYaml(filePath: string): Record<string, unknown> {
  const content = readFileSync(filePath, 'utf-8');
  const parsed = yaml.load(content);
  if (parsed === null || parsed === undefined || typeof parsed !== 'object') {
    throw new Error(`Invalid YAML content in ${filePath}`);
  }
  return parsed as Record<string, unknown>;
}

function applyEnvOverrides(config: Record<string, unknown>): Record<string, unknown> {
  const result = structuredClone(config);

  const overrides: ReadonlyArray<{
    readonly envKey: string;
    readonly path: readonly string[];
    readonly transform?: (value: string) => unknown;
  }> = [
    { envKey: 'POWERTOOLS_SERVICE_NAME', path: ['service', 'name'] },
    { envKey: 'POWERTOOLS_LOG_LEVEL', path: ['logger', 'level'] },
    { envKey: 'Firstance_OBS_SAMPLE_RATE', path: ['logger', 'sampleRate'], transform: parseFloat },
    { envKey: 'Firstance_OBS_METRICS_NAMESPACE', path: ['metrics', 'namespace'] },
  ];

  for (const override of overrides) {
    const envValue = process.env[override.envKey];
    if (envValue !== undefined) {
      setNestedValue(result, override.path, override.transform ? override.transform(envValue) : envValue);
    }
  }

  return result;
}

function setNestedValue(obj: Record<string, unknown>, path: readonly string[], value: unknown): void {
  let current: Record<string, unknown> = obj;
  for (let i = 0; i < path.length - 1; i++) {
    const key = path[i]!;
    if (current[key] === undefined || typeof current[key] !== 'object') {
      current[key] = {};
    }
    current = current[key] as Record<string, unknown>;
  }
  const lastKey = path[path.length - 1]!;
  current[lastKey] = value;
}
