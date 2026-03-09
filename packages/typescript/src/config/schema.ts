import { z } from 'zod';

const logLevelSchema = z.enum(['DEBUG', 'INFO', 'WARN', 'ERROR']);

const loggerSchema = z.object({
  level: logLevelSchema.default('INFO'),
  sampleRate: z.number().min(0).max(1).default(1.0),
  persistentKeys: z.record(z.string(), z.string()).default({}),
}).prefault({});

const tracerSchema = z.object({
  enabled: z.boolean().default(true),
  captureHTTPS: z.boolean().default(true),
}).prefault({});

const metricsSchema = z.object({
  namespace: z.string().default('Default'),
  captureColdStart: z.boolean().default(true),
}).prefault({});

export const configSchema = z.object({
  logger: loggerSchema,
  tracer: tracerSchema,
  metrics: metricsSchema,
});
