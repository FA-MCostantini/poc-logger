# Dynamic Resource Metadata — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make all OTel Resource fields dynamic (no YAML config for service identity), add `telemetry.sdk.*`, `faas.*`, `process.runtime.version` fields.

**Architecture:** Remove `service` section from config pipeline (schema, loader, DTO, fixtures). Move service discovery to formatter/factory. Add new OTel semantic convention fields sourced from env vars and package metadata. TS SDK version injected at build time via esbuild `define`.

**Tech Stack:** TypeScript (Zod, esbuild, Vitest), PHP (Composer InstalledVersions, PHPUnit 11, PHPStan 8)

---

### Task 1: TS — Remove `service` from config schema

**Files:**
- Modify: `packages/typescript/src/config/schema.ts`
- Modify: `packages/typescript/src/config/types.ts`

**Step 1: Update schema.ts**

Remove `serviceSchema` and `service` from `configSchema`:

```typescript
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
```

**Step 2: Update types.ts**

Remove `POWERTOOLS_SERVICE_NAME` from `ENV_MAPPINGS`:

```typescript
import type { z } from 'zod';
import type { configSchema } from './schema.js';

export type FirstanceConfig = z.infer<typeof configSchema>;

export type LogLevel = 'DEBUG' | 'INFO' | 'WARN' | 'ERROR';

export const ENV_MAPPINGS = {
  'POWERTOOLS_LOG_LEVEL': 'logger.level',
  'Firstance_OBS_SAMPLE_RATE': 'logger.sampleRate',
  'Firstance_OBS_METRICS_NAMESPACE': 'metrics.namespace',
} as const;

export type EnvKey = keyof typeof ENV_MAPPINGS;
```

**Step 3: Commit**

```bash
git add packages/typescript/src/config/schema.ts packages/typescript/src/config/types.ts
git commit -m "refactor(ts): remove service section from config schema"
```

---

### Task 2: TS — Update config loader (remove service logic)

**Files:**
- Modify: `packages/typescript/src/config/loader.ts`

**Step 1: Simplify loader.ts**

Remove `findProjectName()`, `ensureServiceName()`, `POWERTOOLS_SERVICE_NAME` override. Keep only YAML loading and env overrides for logger/tracer/metrics:

```typescript
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
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

function getDefaultConfig(): Record<string, unknown> {
  return {
    logger: { level: 'INFO', sampleRate: 1.0, persistentKeys: {} },
    tracer: { enabled: true, captureHTTPS: true },
    metrics: { namespace: 'Default', captureColdStart: true },
  };
}

function readYaml(filePath: string): Record<string, unknown> {
  if (!existsSync(filePath)) {
    const defaultConfig = getDefaultConfig();
    try {
      writeFileSync(filePath, yaml.dump(defaultConfig), 'utf-8');
    } catch {
      console.warn(`[firstance-obs] Cannot create default config at ${filePath}, using in-memory defaults`);
    }
    return defaultConfig;
  }
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
```

**Step 2: Commit**

```bash
git add packages/typescript/src/config/loader.ts
git commit -m "refactor(ts): remove service discovery from config loader"
```

---

### Task 3: TS — Add SDK version constant and service discovery utility

**Files:**
- Create: `packages/typescript/src/version.ts`
- Create: `packages/typescript/src/service-discovery.ts`
- Modify: `build-cjs.mjs`

**Step 1: Create version.ts**

```typescript
export const SDK_NAME = 'poc-logger';
export const SDK_VERSION: string = '__SDK_VERSION__';
```

The placeholder `__SDK_VERSION__` will be replaced at build time by esbuild.

**Step 2: Create service-discovery.ts**

```typescript
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
```

**Step 3: Update build-cjs.mjs to inject SDK_VERSION**

```javascript
import { build } from 'esbuild';
import { readFileSync } from 'node:fs';

const pkg = JSON.parse(readFileSync('package.json', 'utf-8'));

await build({
  entryPoints: ['packages/typescript/src/index.ts'],
  bundle: true,
  format: 'cjs',
  platform: 'node',
  target: 'node20',
  outfile: 'packages/typescript/dist/cjs/index.js',
  external: ['aws-sdk', '@aws-sdk/*'],
  sourcemap: true,
  define: {
    '__SDK_VERSION__': JSON.stringify(pkg.version),
  },
});

import { writeFileSync } from 'node:fs';
writeFileSync('packages/typescript/dist/cjs/package.json', '{"type":"commonjs"}\n');

console.log('CJS build complete');
```

**Step 4: Commit**

```bash
git add packages/typescript/src/version.ts packages/typescript/src/service-discovery.ts build-cjs.mjs
git commit -m "feat(ts): add SDK version constant and service discovery utility"
```

---

### Task 4: TS — Update OTel formatter with new Resource fields

**Files:**
- Modify: `packages/typescript/src/logger/otel-formatter.ts`
- Modify: `packages/typescript/src/logger/types.ts`

**Step 1: Update types.ts with new Resource fields**

```typescript
export const SEVERITY_MAP = {
  DEBUG: 5,
  INFO: 9,
  WARN: 13,
  ERROR: 17,
} as const;

export type SeverityText = keyof typeof SEVERITY_MAP;

export interface OTelResource {
  readonly 'service.name': string;
  readonly 'service.version': string;
  readonly 'telemetry.sdk.name': string;
  readonly 'telemetry.sdk.version': string;
  readonly 'service.language': 'typescript';
  readonly 'faas.name': string;
  readonly 'faas.version': string;
  readonly 'faas.memory': string;
  readonly 'faas.instance': string;
  readonly 'cloud.provider': 'aws';
  readonly 'cloud.region': string;
  readonly 'process.runtime.version': string;
}

export interface OTelLogRecord {
  readonly Timestamp: string;
  readonly SeverityText: SeverityText;
  readonly SeverityNumber: number;
  readonly Body: string;
  readonly Resource: OTelResource;
  readonly Attributes: Record<string, unknown>;
  readonly TraceId?: string;
  readonly SpanId?: string;
}
```

**Step 2: Update otel-formatter.ts**

```typescript
import { LogFormatter, LogItem } from '@aws-lambda-powertools/logger';
import type { LogAttributes, UnformattedAttributes } from '@aws-lambda-powertools/logger/types';
import { SEVERITY_MAP } from './types.js';
import type { SeverityText, OTelResource } from './types.js';

interface OTelLogFormatterOptions {
  readonly serviceName: string;
  readonly serviceVersion: string;
  readonly sdkName: string;
  readonly sdkVersion: string;
}

export class OTelLogFormatter extends LogFormatter {
  private readonly serviceName: string;
  private readonly serviceVersion: string;
  private readonly sdkName: string;
  private readonly sdkVersion: string;

  public constructor(options: OTelLogFormatterOptions) {
    super();
    this.serviceName = options.serviceName;
    this.serviceVersion = options.serviceVersion;
    this.sdkName = options.sdkName;
    this.sdkVersion = options.sdkVersion;
  }

  public formatAttributes(
    attributes: UnformattedAttributes,
    additionalLogAttributes: LogAttributes
  ): LogItem {
    const severityText = attributes.logLevel as SeverityText;
    const severityNumber = SEVERITY_MAP[severityText] ?? 0;

    const resource: OTelResource = {
      'service.name': this.serviceName,
      'service.version': this.serviceVersion,
      'telemetry.sdk.name': this.sdkName,
      'telemetry.sdk.version': this.sdkVersion,
      'service.language': 'typescript',
      'faas.name': attributes.lambdaContext?.functionName ?? '',
      'faas.version': process.env['AWS_LAMBDA_FUNCTION_VERSION'] ?? '',
      'faas.memory': process.env['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] ?? '',
      'faas.instance': process.env['AWS_LAMBDA_LOG_STREAM_NAME'] ?? '',
      'cloud.provider': 'aws',
      'cloud.region': attributes.awsRegion,
      'process.runtime.version': process.version.replace(/^v/, ''),
    };

    const logRecord: LogAttributes = {
      Timestamp: this.formatTimestamp(attributes.timestamp),
      SeverityText: severityText,
      SeverityNumber: severityNumber,
      Body: attributes.message,
      Resource: resource as unknown as LogAttributes,
      Attributes: {
        cold_start: attributes.lambdaContext?.coldStart,
        aws_request_id: attributes.lambdaContext?.awsRequestId,
        ...additionalLogAttributes,
      } as LogAttributes,
      ...(attributes.xRayTraceId ? { TraceId: attributes.xRayTraceId } : {}),
    };

    return new LogItem({ attributes: logRecord });
  }
}
```

**Step 3: Commit**

```bash
git add packages/typescript/src/logger/otel-formatter.ts packages/typescript/src/logger/types.ts
git commit -m "feat(ts): add telemetry.sdk, faas, process.runtime fields to OTel Resource"
```

---

### Task 5: TS — Update factory to use service discovery

**Files:**
- Modify: `packages/typescript/src/factory.ts`
- Modify: `packages/typescript/src/index.ts`

**Step 1: Update factory.ts**

```typescript
import { Logger } from '@aws-lambda-powertools/logger';
import type { Tracer } from '@aws-lambda-powertools/tracer';
import type { Metrics } from '@aws-lambda-powertools/metrics';
import type { MiddlewareObj } from '@middy/core';
import { loadConfig } from './config/loader.js';
import { OTelLogFormatter } from './logger/otel-formatter.js';
import { createTracer } from './tracer/tracer-factory.js';
import { createMetrics } from './metrics/metrics-factory.js';
import { createMiddlewareChain } from './middleware/middy-chain.js';
import { discoverService } from './service-discovery.js';
import { SDK_NAME, SDK_VERSION } from './version.js';

export interface FirstanceLoggerOptions {
  readonly configPath: string;
}

export interface FirstanceObservability {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  middleware(options?: { logEvent?: boolean }): MiddlewareObj;
}

export function createFirstanceLogger(options: FirstanceLoggerOptions): FirstanceObservability {
  const config = loadConfig({ configPath: options.configPath });
  const service = discoverService();

  const formatter = new OTelLogFormatter({
    serviceName: service.name,
    serviceVersion: service.version,
    sdkName: SDK_NAME,
    sdkVersion: SDK_VERSION,
  });

  const logger = new Logger({
    serviceName: service.name,
    logLevel: config.logger.level,
    sampleRateValue: config.logger.sampleRate,
    persistentLogAttributes: config.logger.persistentKeys,
    logFormatter: formatter,
  });

  const tracer = createTracer({ ...config, serviceName: service.name });
  const metrics = createMetrics({ ...config, serviceName: service.name });

  return {
    logger,
    tracer,
    metrics,
    middleware(mwOptions) {
      return createMiddlewareChain({
        logger,
        tracer,
        metrics,
        captureColdStart: config.metrics.captureColdStart,
        logEvent: mwOptions?.logEvent,
      });
    },
  };
}
```

**Step 2: Update index.ts exports**

Add new exports:

```typescript
// Config
export { loadConfig } from './config/loader.js';
export { configSchema } from './config/schema.js';
export type { FirstanceConfig, LogLevel } from './config/types.js';

// Service Discovery
export { discoverService } from './service-discovery.js';
export type { ServiceInfo } from './service-discovery.js';

// Version
export { SDK_NAME, SDK_VERSION } from './version.js';

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
export { createFirstanceLogger } from './factory.js';
export type { FirstanceLoggerOptions, FirstanceObservability } from './factory.js';

// Re-export middy for consumer convenience
export { default as middy } from '@middy/core';
```

**Step 3: Commit**

```bash
git add packages/typescript/src/factory.ts packages/typescript/src/index.ts
git commit -m "feat(ts): factory uses service discovery and SDK version"
```

---

### Task 6: TS — Update tracer and metrics factories

**Files:**
- Modify: `packages/typescript/src/tracer/tracer-factory.ts`
- Modify: `packages/typescript/src/metrics/metrics-factory.ts`

**Step 1: Check current tracer-factory.ts and metrics-factory.ts signatures**

These currently receive `config` with `config.service.name`. Update them to accept `serviceName` separately since `config` no longer has `service`.

Read the current files first, then update to accept `{ ...config, serviceName }` pattern or adjust the interface.

The factory in Task 5 passes `{ ...config, serviceName: service.name }`. These factories need to accept an object with `serviceName` at the top level plus the config fields they need.

**Step 2: Update tracer-factory.ts**

Change from `config.service.name` to `config.serviceName`:

```typescript
// tracer-factory.ts — update the config access pattern
// from: serviceName: config.service.name
// to: serviceName: config.serviceName
```

**Step 3: Update metrics-factory.ts**

Same pattern change.

**Step 4: Commit**

```bash
git add packages/typescript/src/tracer/tracer-factory.ts packages/typescript/src/metrics/metrics-factory.ts
git commit -m "refactor(ts): tracer and metrics accept serviceName directly"
```

---

### Task 7: TS — Update test fixtures and config loader tests

**Files:**
- Modify: `packages/typescript/tests/fixtures/config.valid.yaml`
- Modify: `packages/typescript/tests/fixtures/config.minimal.yaml`
- Modify: `packages/typescript/tests/fixtures/config.no-service-name.yaml`
- Modify: `packages/typescript/tests/unit/config/loader.test.ts`
- Modify: `packages/typescript/tests/unit/config/schema.test.ts`

**Step 1: Update YAML fixtures — remove `service:` section**

`config.valid.yaml`:
```yaml
logger:
  level: "DEBUG"
  sampleRate: 0.5
  persistentKeys:
    team: "test-team"

tracer:
  enabled: true
  captureHTTPS: false

metrics:
  namespace: "TestNS"
  captureColdStart: false
```

`config.minimal.yaml`:
```yaml
logger:
  level: "INFO"
```

Delete `config.no-service-name.yaml` (no longer relevant).

**Step 2: Update loader.test.ts**

Remove all assertions on `config.service.name` and `config.service.version`. Remove test for `POWERTOOLS_SERVICE_NAME` env override. Remove test for missing service name. Update remaining tests:

- `should load a valid full config from YAML` — remove service assertions
- `should load a minimal config and apply defaults` — remove service assertions
- `should create default config when YAML is missing` — remove service assertions
- Remove `should use service name from package.json when YAML has no service.name`
- `should override config with environment variables` — remove `POWERTOOLS_SERVICE_NAME`
- `should give env vars precedence over YAML values` — remove service.name assertion

**Step 3: Update schema.test.ts**

Remove any tests that validate the `service` section of the schema.

**Step 4: Commit**

```bash
git add packages/typescript/tests/
git commit -m "test(ts): update fixtures and tests for service-less config"
```

---

### Task 8: TS — Update formatter and factory tests

**Files:**
- Modify: `packages/typescript/tests/unit/logger/otel-formatter.test.ts`
- Modify: `packages/typescript/tests/unit/factory.test.ts`

**Step 1: Update otel-formatter.test.ts**

Update formatter constructor to use new options shape. Add assertions for new Resource fields:

```typescript
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { OTelLogFormatter } from '../../../src/logger/otel-formatter.js';
import type { UnformattedAttributes } from '@aws-lambda-powertools/logger/types';

function makeAttributes(overrides: Partial<UnformattedAttributes> = {}): UnformattedAttributes {
  return {
    message: 'test message',
    logLevel: 'INFO',
    serviceName: 'test-service',
    timestamp: new Date('2026-03-06T10:00:00.000Z'),
    environment: '',
    awsRegion: 'eu-south-1',
    xRayTraceId: '1-abc-def',
    sampleRateValue: 1,
    lambdaContext: {
      functionName: 'my-lambda',
      functionVersion: '$LATEST',
      invokedFunctionArn: 'arn:aws:lambda:eu-south-1:123456:function:my-lambda',
      memoryLimitInMB: 128,
      awsRequestId: 'req-123',
      tenantId: '',
      coldStart: true,
    },
    ...overrides,
  };
}

describe('OTelLogFormatter', () => {
  const originalEnv = { ...process.env };

  beforeEach(() => {
    process.env = { ...originalEnv };
    process.env['AWS_LAMBDA_FUNCTION_VERSION'] = '$LATEST';
    process.env['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] = '512';
    process.env['AWS_LAMBDA_LOG_STREAM_NAME'] = '2026/03/09/[$LATEST]abc123';
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  const formatter = new OTelLogFormatter({
    serviceName: 'test-service',
    serviceVersion: '1.0.0',
    sdkName: 'poc-logger',
    sdkVersion: '0.2.3',
  });

  it('should produce OTel-compliant log record structure', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    expect(output.Timestamp).toBe('2026-03-06T10:00:00.000Z');
    expect(output.SeverityText).toBe('INFO');
    expect(output.SeverityNumber).toBe(9);
    expect(output.Body).toBe('test message');
  });

  it('should include all Resource fields', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;
    expect(resource['service.name']).toBe('test-service');
    expect(resource['service.version']).toBe('1.0.0');
    expect(resource['telemetry.sdk.name']).toBe('poc-logger');
    expect(resource['telemetry.sdk.version']).toBe('0.2.3');
    expect(resource['service.language']).toBe('typescript');
    expect(resource['faas.name']).toBe('my-lambda');
    expect(resource['faas.version']).toBe('$LATEST');
    expect(resource['faas.memory']).toBe('512');
    expect(resource['faas.instance']).toBe('2026/03/09/[$LATEST]abc123');
    expect(resource['cloud.provider']).toBe('aws');
    expect(resource['cloud.region']).toBe('eu-south-1');
    expect(resource['process.runtime.version']).toMatch(/^\d+\.\d+\.\d+/);
  });

  it('should map severity levels correctly', () => {
    const levels = [['DEBUG', 5], ['INFO', 9], ['WARN', 13], ['ERROR', 17]] as const;
    for (const [level, number] of levels) {
      const attrs = makeAttributes({ logLevel: level });
      const logItem = formatter.formatAttributes(attrs, {});
      const output = logItem.getAttributes();
      expect(output.SeverityText).toBe(level);
      expect(output.SeverityNumber).toBe(number);
    }
  });

  it('should include TraceId from X-Ray trace', () => {
    const attrs = makeAttributes({ xRayTraceId: '1-abc-def' });
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    expect(output.TraceId).toBe('1-abc-def');
  });

  it('should merge additional log attributes into Attributes', () => {
    const attrs = makeAttributes();
    const additional = { customKey: 'customValue', orderId: 42 };
    const logItem = formatter.formatAttributes(attrs, additional);
    const output = logItem.getAttributes();
    const attributes = output.Attributes as Record<string, unknown>;
    expect(attributes.customKey).toBe('customValue');
    expect(attributes.orderId).toBe(42);
  });

  it('should include cold_start in Attributes', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const attributes = output.Attributes as Record<string, unknown>;
    expect(attributes.cold_start).toBe(true);
  });

  it('should handle missing lambda context gracefully', () => {
    const attrs = makeAttributes({ lambdaContext: undefined });
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;
    expect(resource['faas.name']).toBe('');
  });

  it('should default faas env fields to empty string when not set', () => {
    delete process.env['AWS_LAMBDA_FUNCTION_VERSION'];
    delete process.env['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'];
    delete process.env['AWS_LAMBDA_LOG_STREAM_NAME'];
    const f = new OTelLogFormatter({
      serviceName: 'test',
      serviceVersion: '1.0.0',
      sdkName: 'poc-logger',
      sdkVersion: '0.2.3',
    });
    const attrs = makeAttributes();
    const logItem = f.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;
    expect(resource['faas.version']).toBe('');
    expect(resource['faas.memory']).toBe('');
    expect(resource['faas.instance']).toBe('');
  });
});
```

**Step 2: Update factory.test.ts**

Remove dependency on `config.valid.yaml` having `service` section (it no longer does). Tests should still pass since factory now discovers service from package.json:

```typescript
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
```

**Step 3: Run TS tests**

Run: `npx vitest run`
Expected: ALL PASS

**Step 4: Commit**

```bash
git add packages/typescript/tests/
git commit -m "test(ts): update formatter and factory tests for new Resource fields"
```

---

### Task 9: PHP — Remove `service` from config pipeline

**Files:**
- Modify: `packages/php/src/Config/ConfigDTO.php`
- Modify: `packages/php/src/Config/ConfigSchema.php`
- Modify: `packages/php/src/Config/ConfigLoader.php`

**Step 1: Update ConfigDTO — remove serviceName and serviceVersion**

```php
<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

final readonly class ConfigDTO
{
    /**
     * @param array<string, string> $persistentKeys
     */
    public function __construct(
        public string $logLevel = 'INFO',
        public float $logSampleRate = 1.0,
        public array $persistentKeys = [],
        public bool $tracerEnabled = true,
        public bool $tracerCaptureHTTPS = true,
        public string $metricsNamespace = 'Default',
        public bool $metricsCaptureColdStart = true,
    ) {}
}
```

**Step 2: Update ConfigSchema — remove service validation**

Remove `$serviceName` and `$serviceVersion` extraction. Remove the `service` required validation. Update `ConfigDTO` constructor call accordingly.

**Step 3: Update ConfigLoader — remove service discovery and POWERTOOLS_SERVICE_NAME**

Remove `findProjectName()`, `findProjectVersion()`, `ensureServiceName()`. Remove `POWERTOOLS_SERVICE_NAME` from `ENV_MAPPINGS`. Simplify `getDefaultConfig()` to not include `service`.

**Step 4: Commit**

```bash
git add packages/php/src/Config/
git commit -m "refactor(php): remove service section from config pipeline"
```

---

### Task 10: PHP — Add ServiceDiscovery class

**Files:**
- Create: `packages/php/src/Config/ServiceDiscovery.php`

**Step 1: Create ServiceDiscovery.php**

```php
<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

use Composer\InstalledVersions;

final class ServiceDiscovery
{
    public static function serviceName(): string
    {
        $root = InstalledVersions::getRootPackage();
        $name = $root['name'];
        if ($name !== '' && $name !== '__root__') {
            $parts = explode('/', $name);
            return end($parts) ?: 'unknown';
        }

        return 'unknown';
    }

    public static function serviceVersion(): string
    {
        $root = InstalledVersions::getRootPackage();
        $version = $root['pretty_version'];
        if ($version !== '' && !str_starts_with($version, 'dev-') && !str_contains($version, 'no-version-set')) {
            return $version;
        }

        return '0.0.0';
    }

    public static function sdkName(): string
    {
        return 'poc-logger';
    }

    public static function sdkVersion(): string
    {
        try {
            $version = InstalledVersions::getVersion('firstance/poc-logger');
            return $version ?? '0.0.0';
        } catch (\OutOfBoundsException) {
            return '0.0.0';
        }
    }
}
```

**Step 2: Commit**

```bash
git add packages/php/src/Config/ServiceDiscovery.php
git commit -m "feat(php): add ServiceDiscovery class"
```

---

### Task 11: PHP — Update OTel formatter with new Resource fields

**Files:**
- Modify: `packages/php/src/Logger/OTelCloudWatchFormatter.php`

**Step 1: Update OTelCloudWatchFormatter**

```php
<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

final class OTelCloudWatchFormatter extends JsonFormatter
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $serviceVersion,
        private readonly string $sdkName,
        private readonly string $sdkVersion,
        private readonly string $region,
    ) {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        $severity = Severity::fromMonologLevel($record->level->value);

        $otelRecord = [
            'Timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'SeverityText' => $severity->name,
            'SeverityNumber' => $severity->value,
            'Body' => $record->message,
            'Resource' => [
                'service.name' => $this->serviceName,
                'service.version' => $this->serviceVersion,
                'telemetry.sdk.name' => $this->sdkName,
                'telemetry.sdk.version' => $this->sdkVersion,
                'service.language' => 'php',
                'faas.name' => $record->extra['faas.name'] ?? '',
                'faas.version' => getenv('AWS_LAMBDA_FUNCTION_VERSION') ?: '',
                'faas.memory' => getenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE') ?: '',
                'faas.instance' => getenv('AWS_LAMBDA_LOG_STREAM_NAME') ?: '',
                'cloud.provider' => 'aws',
                'cloud.region' => $this->region,
                'process.runtime.version' => PHP_VERSION,
            ],
            'Attributes' => array_filter(
                array_merge(
                    [
                        'cold_start' => $record->extra['cold_start'] ?? null,
                        'aws_request_id' => $record->extra['aws_request_id'] ?? null,
                    ],
                    $record->context,
                ),
                static fn (mixed $v): bool => $v !== null,
            ),
        ];

        if (isset($record->extra['trace_id'])) {
            $otelRecord['TraceId'] = $record->extra['trace_id'];
        }

        return $this->toJson($otelRecord) . "\n";
    }
}
```

**Step 2: Commit**

```bash
git add packages/php/src/Logger/OTelCloudWatchFormatter.php
git commit -m "feat(php): add telemetry.sdk, faas, process.runtime fields to OTel Resource"
```

---

### Task 12: PHP — Update factory to use ServiceDiscovery

**Files:**
- Modify: `packages/php/src/FirstanceLoggerFactory.php`

**Step 1: Update FirstanceLoggerFactory**

```php
<?php

declare(strict_types=1);

namespace Firstance\LambdaObs;

use Firstance\LambdaObs\Config\ConfigDTO;
use Firstance\LambdaObs\Config\ConfigLoader;
use Firstance\LambdaObs\Config\ServiceDiscovery;
use Firstance\LambdaObs\Logger\ColdStartProcessor;
use Firstance\LambdaObs\Logger\LambdaContextProcessor;
use Firstance\LambdaObs\Logger\OTelCloudWatchFormatter;
use Firstance\LambdaObs\Metrics\EmfMetricsEmitter;
use Firstance\LambdaObs\Tracer\XRayTracerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class FirstanceLoggerFactory
{
    public static function create(string $configPath = './config.yaml'): FirstanceObservability
    {
        $config = ConfigLoader::load($configPath);

        return self::createFromConfig($config);
    }

    public static function createFromConfig(ConfigDTO $config): FirstanceObservability
    {
        $region = getenv('AWS_REGION') ?: '';
        $serviceName = ServiceDiscovery::serviceName();
        $serviceVersion = ServiceDiscovery::serviceVersion();

        $formatter = new OTelCloudWatchFormatter(
            serviceName: $serviceName,
            serviceVersion: $serviceVersion,
            sdkName: ServiceDiscovery::sdkName(),
            sdkVersion: ServiceDiscovery::sdkVersion(),
            region: $region,
        );

        /** @var 'DEBUG'|'INFO'|'WARNING'|'ERROR'|'CRITICAL'|'ALERT'|'EMERGENCY' $levelName */
        $levelName = $config->logLevel === 'WARN' ? 'WARNING' : $config->logLevel;
        $handler = new StreamHandler('php://stdout', Level::fromName($levelName));
        $handler->setFormatter($formatter);

        $logger = new Logger($serviceName);
        $logger->pushHandler($handler);
        $logger->pushProcessor(new LambdaContextProcessor());
        $logger->pushProcessor(new ColdStartProcessor());

        $tracer = new XRayTracerFactory($config, $serviceName);
        $metrics = new EmfMetricsEmitter($config, $serviceName);

        return new FirstanceObservability(
            logger: $logger,
            tracer: $tracer,
            metrics: $metrics,
        );
    }
}
```

Note: `XRayTracerFactory` and `EmfMetricsEmitter` will need to accept `$serviceName` as a separate parameter since `ConfigDTO` no longer has it. Check their constructors and update accordingly.

**Step 2: Commit**

```bash
git add packages/php/src/FirstanceLoggerFactory.php
git commit -m "feat(php): factory uses ServiceDiscovery for service identity"
```

---

### Task 13: PHP — Update Tracer and Metrics to accept serviceName

**Files:**
- Modify: `packages/php/src/Tracer/XRayTracerFactory.php`
- Modify: `packages/php/src/Metrics/EmfMetricsEmitter.php`

**Step 1: Read current constructors and update to accept `string $serviceName` parameter**

These classes currently get `serviceName` from `ConfigDTO`. Update their constructors to accept it as a separate string parameter.

**Step 2: Commit**

```bash
git add packages/php/src/Tracer/ packages/php/src/Metrics/
git commit -m "refactor(php): tracer and metrics accept serviceName directly"
```

---

### Task 14: PHP — Update test fixtures and config tests

**Files:**
- Modify: `packages/php/tests/Fixtures/config.valid.yaml`
- Modify: `packages/php/tests/Fixtures/config.minimal.yaml`
- Delete: `packages/php/tests/Fixtures/config.no-service-name.yaml`
- Modify: `packages/php/tests/Unit/Config/ConfigLoaderTest.php`
- Modify: `packages/php/tests/Unit/Config/ConfigDTOTest.php`
- Modify: `packages/php/tests/Unit/Config/ConfigSchemaTest.php`

**Step 1: Update YAML fixtures — remove `service:` section**

Same content as TS fixtures (they share the same schema).

**Step 2: Update ConfigDTOTest — remove serviceName/serviceVersion assertions**

**Step 3: Update ConfigLoaderTest — remove all service-related tests and env overrides**

**Step 4: Update ConfigSchemaTest — remove service validation tests**

**Step 5: Commit**

```bash
git add packages/php/tests/
git commit -m "test(php): update fixtures and tests for service-less config"
```

---

### Task 15: PHP — Update formatter and factory tests

**Files:**
- Modify: `packages/php/tests/Unit/Logger/OTelCloudWatchFormatterTest.php`
- Modify: `packages/php/tests/Unit/FirstanceLoggerFactoryTest.php`

**Step 1: Update OTelCloudWatchFormatterTest**

Update constructor call with new parameters. Add assertions for new Resource fields:

```php
protected function setUp(): void
{
    putenv('AWS_LAMBDA_FUNCTION_VERSION=$LATEST');
    putenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE=512');
    putenv('AWS_LAMBDA_LOG_STREAM_NAME=2026/03/09/[$LATEST]abc123');

    $this->formatter = new OTelCloudWatchFormatter(
        serviceName: 'test-service',
        serviceVersion: '1.0.0',
        sdkName: 'poc-logger',
        sdkVersion: '0.2.3',
        region: 'eu-south-1',
    );
}

protected function tearDown(): void
{
    putenv('AWS_LAMBDA_FUNCTION_VERSION');
    putenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE');
    putenv('AWS_LAMBDA_LOG_STREAM_NAME');
}
```

Add test `testIncludesAllResourceFields()` asserting `telemetry.sdk.name`, `telemetry.sdk.version`, `faas.version`, `faas.memory`, `faas.instance`, `process.runtime.version`.

Add test `testDefaultsFaasFieldsWhenEnvNotSet()` clearing those env vars and asserting empty strings.

**Step 2: Update FirstanceLoggerFactoryTest**

`createFromConfig` no longer receives `serviceName` in `ConfigDTO`. Update test to just pass config without service fields. The factory will use `ServiceDiscovery` internally.

**Step 3: Run PHP tests**

Run: `docker build -t firstance-obs-php -f packages/php/tests/Dockerfile . && docker run --rm firstance-obs-php`
Expected: ALL PASS

**Step 4: Commit**

```bash
git add packages/php/tests/
git commit -m "test(php): update formatter and factory tests for new Resource fields"
```

---

### Task 16: Update shared config schema and example

**Files:**
- Modify: `shared/schemas/config-schema.json`
- Modify: `shared/config.example.yaml`

**Step 1: Update config-schema.json — remove `service` requirement**

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://firstance.it/poc-logger/config-schema.json",
  "title": "poc-logger Configuration",
  "type": "object",
  "properties": {
    "logger": {
      "type": "object",
      "properties": {
        "level": {
          "type": "string",
          "enum": ["DEBUG", "INFO", "WARN", "ERROR"],
          "default": "INFO",
          "description": "Log level. Override via env: POWERTOOLS_LOG_LEVEL"
        },
        "sampleRate": {
          "type": "number",
          "minimum": 0,
          "maximum": 1,
          "default": 1.0,
          "description": "Log sampling rate (0.0 to 1.0)"
        },
        "persistentKeys": {
          "type": "object",
          "additionalProperties": {
            "type": "string"
          },
          "default": {},
          "description": "Key-value pairs added to every log record"
        }
      },
      "additionalProperties": false,
      "default": {}
    },
    "tracer": {
      "type": "object",
      "properties": {
        "enabled": {
          "type": "boolean",
          "default": true
        },
        "captureHTTPS": {
          "type": "boolean",
          "default": true
        }
      },
      "additionalProperties": false,
      "default": {}
    },
    "metrics": {
      "type": "object",
      "properties": {
        "namespace": {
          "type": "string",
          "default": "Default",
          "description": "CloudWatch Metrics namespace"
        },
        "captureColdStart": {
          "type": "boolean",
          "default": true
        }
      },
      "additionalProperties": false,
      "default": {}
    }
  },
  "additionalProperties": false
}
```

**Step 2: Update config.example.yaml**

```yaml
# poc-logger Configuration
# service.name and service.version are auto-discovered from package.json / composer.json
#
# Env overrides (12-factor):
#   POWERTOOLS_LOG_LEVEL  -> logger.level
#   Firstance_OBS_SAMPLE_RATE -> logger.sampleRate
#   Firstance_OBS_METRICS_NAMESPACE -> metrics.namespace

logger:
  level: "INFO"
  sampleRate: 0.1
  persistentKeys:
    team: "integrations"
    partner: "athora"

tracer:
  enabled: true
  captureHTTPS: true

metrics:
  namespace: "FirstanceFileDelivery"
  captureColdStart: true
```

**Step 3: Commit**

```bash
git add shared/
git commit -m "docs: update shared config schema and example — remove service section"
```

---

### Task 17: Update cross-language test

**Files:**
- Modify: `tests/emit-log-ts.ts`
- Modify: `tests/cross-language-test.sh`

**Step 1: Update emit-log-ts.ts**

Update to use new formatter constructor:

```typescript
import { Logger } from '@aws-lambda-powertools/logger';
import { OTelLogFormatter } from './packages/typescript/src/logger/otel-formatter.js';

const formatter = new OTelLogFormatter({
  serviceName: 'cross-lang-test',
  serviceVersion: '1.0.0',
  sdkName: 'poc-logger',
  sdkVersion: '0.2.3',
});

const logger = new Logger({
  serviceName: 'cross-lang-test',
  logLevel: 'INFO',
  logFormatter: formatter,
});

logger.info('test log message', { orderId: 42, status: 'ok' });
```

**Step 2: Update cross-language-test.sh**

Update the key comparison to account for new fields. The filter should still exclude `service.language` and `process.runtime.version` (differ by language/runtime). Add `process.runtime.version` to the exclusion list:

```bash
# Compare (ignoring language-specific fields)
TS_FILTERED=$(echo "$TS_KEYS" | grep -v "service.language" | grep -v "process.runtime.version" | sort)
PHP_FILTERED=$(echo "$PHP_KEYS" | grep -v "service.language" | grep -v "process.runtime.version" | sort)
```

**Step 3: Commit**

```bash
git add tests/
git commit -m "test: update cross-language test for new Resource structure"
```

---

### Task 18: Update documentation

**Files:**
- Modify: `README.md`
- Modify: `docs/TEST_ENVIRONMENT.md`
- Modify: `docs/DEPLOY.md`

**Step 1: Update README.md**

- Update the example log output to show all new Resource fields
- Remove any reference to `service.name` / `service.version` in `config.yaml`
- Document that service identity is auto-discovered from package.json / composer.json
- Document new Resource fields and their sources

**Step 2: Update TEST_ENVIRONMENT.md**

- Note that config.yaml no longer contains `service` section
- Update any example YAML shown in the doc

**Step 3: Update DEPLOY.md**

- Remove references to configuring `service.name` in YAML
- Document that service name comes from package.json / composer.json `name` field

**Step 4: Commit**

```bash
git add README.md docs/
git commit -m "docs: update documentation for dynamic Resource metadata"
```

---

### Task 19: Build, full test run, and version bump

**Step 1: Build TS**

Run: `npm run build`
Expected: ESM + CJS build succeed

**Step 2: Run TS tests**

Run: `npm test`
Expected: All vitest tests + CJS smoke test pass

**Step 3: Build and run PHP tests**

Run: `docker build --network=host -t firstance-obs-php -f packages/php/tests/Dockerfile . && docker run --rm firstance-obs-php`
Expected: All PHP tests pass, PHPStan level 8 clean

**Step 4: Run cross-language test**

Run: `bash tests/cross-language-test.sh`
Expected: ALL CROSS-LANGUAGE CHECKS PASSED

**Step 5: Version bump**

Update version to `0.3.0` in `package.json`, `composer.json`, regenerate `package-lock.json`.

**Step 6: Commit and tag**

```bash
git add -A
git commit -m "v0.3.0 — dynamic Resource metadata, telemetry.sdk and faas fields"
git tag v0.3.0
```
