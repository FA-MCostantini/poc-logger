// Config
export { loadConfig } from './config/loader.js';
export { configSchema } from './config/schema.js';
export type { BperConfig, LogLevel } from './config/types.js';

// Logger
export { OTelLogFormatter } from './logger/otel-formatter.js';
export type { OTelLogRecord, OTelResource, SeverityText } from './logger/types.js';

// Tracer
export { createTracer } from './tracer/tracer-factory.js';

// Metrics
export { createMetrics } from './metrics/metrics-factory.js';

// Middleware
export { createMiddlewareChain } from './middleware/middy-chain.js';
export type { MiddlewareChainOptions } from './middleware/middy-chain.js';

// Factory (main entry point)
export { createBperLogger } from './factory.js';
export type { BperLoggerOptions, BperObservability } from './factory.js';
