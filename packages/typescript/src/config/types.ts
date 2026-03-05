import type { z } from 'zod';
import type { configSchema } from './schema.js';

export type BperConfig = z.infer<typeof configSchema>;

export type LogLevel = 'DEBUG' | 'INFO' | 'WARN' | 'ERROR';

export const ENV_MAPPINGS = {
  'POWERTOOLS_LOG_LEVEL': 'logger.level',
  'POWERTOOLS_SERVICE_NAME': 'service.name',
  'BPER_OBS_SAMPLE_RATE': 'logger.sampleRate',
  'BPER_OBS_METRICS_NAMESPACE': 'metrics.namespace',
} as const;

export type EnvKey = keyof typeof ENV_MAPPINGS;
