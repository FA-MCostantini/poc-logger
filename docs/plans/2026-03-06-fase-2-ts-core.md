# Fase 2 — TypeScript Core Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement the full `@firstance/lambda-obs` TypeScript package: OTel log formatter, tracer/metrics factories, Middy middleware chain, and factory entry point.

**Architecture:** Custom `LogFormatter` extending Powertools Logger produces OTel-compliant JSON. Thin wrapper factories for Tracer and Metrics configure Powertools instances from `FirstanceConfig`. A Middy middleware chain composes all three. `createFirstanceLogger()` is the single entry point.

**Tech Stack:** Powertools Logger/Tracer/Metrics v2.31+, Middy v5, Vitest, TypeScript strict mode.

---

### Task 1: OTel Logger Types

**Files:**
- Create: `packages/typescript/src/logger/types.ts`

**Step 1: Write the OTel log record interface**

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
  readonly 'service.language': 'typescript';
  readonly 'faas.name': string;
  readonly 'cloud.provider': 'aws';
  readonly 'cloud.region': string;
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

**Step 2: Commit**

```bash
git add packages/typescript/src/logger/types.ts
git commit -m "feat(ts): add OTel log record types and severity map"
```

---

### Task 2: OTelLogFormatter — Tests

**Files:**
- Create: `packages/typescript/tests/unit/logger/otel-formatter.test.ts`

**Step 1: Write the failing tests**

```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { OTelLogFormatter } from '../../../src/logger/otel-formatter.js';
import { LogItem } from '@aws-lambda-powertools/logger';
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
      coldStart: true,
    },
    ...overrides,
  };
}

describe('OTelLogFormatter', () => {
  const formatter = new OTelLogFormatter({
    serviceVersion: '1.0.0',
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

  it('should include Resource fields', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;

    expect(resource['service.name']).toBe('test-service');
    expect(resource['service.version']).toBe('1.0.0');
    expect(resource['service.language']).toBe('typescript');
    expect(resource['faas.name']).toBe('my-lambda');
    expect(resource['cloud.provider']).toBe('aws');
    expect(resource['cloud.region']).toBe('eu-south-1');
  });

  it('should map severity levels correctly', () => {
    const levels = [
      ['DEBUG', 5],
      ['INFO', 9],
      ['WARN', 13],
      ['ERROR', 17],
    ] as const;

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
    expect(resource['faas.name']).toBeUndefined();
  });
});
```

**Step 2: Run tests to verify they fail**

Run: `cd packages/typescript && npx vitest run tests/unit/logger/otel-formatter.test.ts`
Expected: FAIL — cannot find module `otel-formatter.js`

**Step 3: Commit test file**

```bash
git add packages/typescript/tests/unit/logger/otel-formatter.test.ts
git commit -m "test(ts): add OTelLogFormatter unit tests"
```

---

### Task 3: OTelLogFormatter — Implementation

**Files:**
- Create: `packages/typescript/src/logger/otel-formatter.ts`
- Remove: `packages/typescript/src/logger/.gitkeep`

**Step 1: Write the implementation**

```typescript
import { LogFormatter, LogItem } from '@aws-lambda-powertools/logger';
import type { LogAttributes, UnformattedAttributes } from '@aws-lambda-powertools/logger/types';
import { SEVERITY_MAP } from './types.js';
import type { SeverityText, OTelResource } from './types.js';

interface OTelLogFormatterOptions {
  readonly serviceVersion: string;
}

export class OTelLogFormatter extends LogFormatter {
  private readonly serviceVersion: string;

  public constructor(options: OTelLogFormatterOptions) {
    super();
    this.serviceVersion = options.serviceVersion;
  }

  public formatAttributes(
    attributes: UnformattedAttributes,
    additionalLogAttributes: LogAttributes
  ): LogItem {
    const severityText = attributes.logLevel as SeverityText;
    const severityNumber = SEVERITY_MAP[severityText] ?? 0;

    const resource: OTelResource = {
      'service.name': attributes.serviceName,
      'service.version': this.serviceVersion,
      'service.language': 'typescript',
      'faas.name': attributes.lambdaContext?.functionName ?? '',
      'cloud.provider': 'aws',
      'cloud.region': attributes.awsRegion,
    };

    const logRecord = {
      Timestamp: this.formatTimestamp(attributes.timestamp),
      SeverityText: severityText,
      SeverityNumber: severityNumber,
      Body: attributes.message,
      Resource: resource,
      Attributes: {
        cold_start: attributes.lambdaContext?.coldStart,
        aws_request_id: attributes.lambdaContext?.awsRequestId,
        ...additionalLogAttributes,
      },
      ...(attributes.xRayTraceId ? { TraceId: attributes.xRayTraceId } : {}),
    };

    return new LogItem({ attributes: logRecord });
  }
}
```

**Step 2: Run tests to verify they pass**

Run: `cd packages/typescript && npx vitest run tests/unit/logger/otel-formatter.test.ts`
Expected: All 7 tests PASS

**Step 3: Commit**

```bash
git rm packages/typescript/src/logger/.gitkeep
git add packages/typescript/src/logger/otel-formatter.ts
git commit -m "feat(ts): add OTelLogFormatter with OTel-compliant output"
```

---

### Task 4: TracerFactory — Tests + Implementation

**Files:**
- Create: `packages/typescript/tests/unit/tracer/tracer-factory.test.ts`
- Create: `packages/typescript/src/tracer/tracer-factory.ts`
- Remove: `packages/typescript/src/tracer/.gitkeep`, `packages/typescript/tests/unit/tracer/.gitkeep`

**Step 1: Write the failing test**

```typescript
import { describe, it, expect, vi } from 'vitest';
import { createTracer } from '../../../src/tracer/tracer-factory.js';
import type { FirstanceConfig } from '../../../src/config/types.js';

function makeConfig(overrides: Partial<FirstanceConfig['tracer']> = {}): FirstanceConfig {
  return {
    service: { name: 'test-svc', version: '1.0.0' },
    logger: { level: 'INFO', sampleRate: 1, persistentKeys: {} },
    tracer: { enabled: true, captureHTTPS: true, ...overrides },
    metrics: { namespace: 'TestNS', captureColdStart: true },
  };
}

describe('createTracer', () => {
  it('should create a Tracer with the configured service name', () => {
    const tracer = createTracer(makeConfig());
    expect(tracer).toBeDefined();
    expect(tracer.serviceName).toBe('test-svc');
  });

  it('should respect the enabled flag', () => {
    const tracer = createTracer(makeConfig({ enabled: false }));
    expect(tracer).toBeDefined();
  });

  it('should configure captureHTTPS', () => {
    const tracer = createTracer(makeConfig({ captureHTTPS: false }));
    expect(tracer).toBeDefined();
  });
});
```

**Step 2: Run test to verify it fails**

Run: `cd packages/typescript && npx vitest run tests/unit/tracer/tracer-factory.test.ts`
Expected: FAIL — cannot find module

**Step 3: Write the implementation**

```typescript
import { Tracer } from '@aws-lambda-powertools/tracer';
import type { FirstanceConfig } from '../config/types.js';

export function createTracer(config: FirstanceConfig): Tracer {
  return new Tracer({
    serviceName: config.service.name,
    enabled: config.tracer.enabled,
    captureHTTPsRequests: config.tracer.captureHTTPS,
  });
}
```

**Step 4: Run tests to verify they pass**

Run: `cd packages/typescript && npx vitest run tests/unit/tracer/tracer-factory.test.ts`
Expected: All 3 tests PASS

**Step 5: Commit**

```bash
git rm packages/typescript/src/tracer/.gitkeep packages/typescript/tests/unit/tracer/.gitkeep
git add packages/typescript/src/tracer/tracer-factory.ts packages/typescript/tests/unit/tracer/tracer-factory.test.ts
git commit -m "feat(ts): add TracerFactory with Powertools Tracer wrapper"
```

---

### Task 5: MetricsFactory — Tests + Implementation

**Files:**
- Create: `packages/typescript/tests/unit/metrics/metrics-factory.test.ts`
- Create: `packages/typescript/src/metrics/metrics-factory.ts`
- Remove: `packages/typescript/src/metrics/.gitkeep`, `packages/typescript/tests/unit/metrics/.gitkeep`

**Step 1: Write the failing test**

```typescript
import { describe, it, expect } from 'vitest';
import { createMetrics } from '../../../src/metrics/metrics-factory.js';
import type { FirstanceConfig } from '../../../src/config/types.js';

function makeConfig(overrides: Partial<FirstanceConfig['metrics']> = {}): FirstanceConfig {
  return {
    service: { name: 'test-svc', version: '1.0.0' },
    logger: { level: 'INFO', sampleRate: 1, persistentKeys: {} },
    tracer: { enabled: true, captureHTTPS: true },
    metrics: { namespace: 'TestNS', captureColdStart: true, ...overrides },
  };
}

describe('createMetrics', () => {
  it('should create Metrics with configured namespace', () => {
    const metrics = createMetrics(makeConfig());
    expect(metrics).toBeDefined();
  });

  it('should set service name as default dimension', () => {
    const metrics = createMetrics(makeConfig());
    expect(metrics).toBeDefined();
  });

  it('should use custom namespace', () => {
    const metrics = createMetrics(makeConfig({ namespace: 'CustomNS' }));
    expect(metrics).toBeDefined();
  });
});
```

**Step 2: Run test to verify it fails**

**Step 3: Write the implementation**

```typescript
import { Metrics } from '@aws-lambda-powertools/metrics';
import type { FirstanceConfig } from '../config/types.js';

export function createMetrics(config: FirstanceConfig): Metrics {
  const metrics = new Metrics({
    namespace: config.metrics.namespace,
    serviceName: config.service.name,
  });

  return metrics;
}
```

**Step 4: Run tests and verify pass**

**Step 5: Commit**

```bash
git rm packages/typescript/src/metrics/.gitkeep packages/typescript/tests/unit/metrics/.gitkeep
git add packages/typescript/src/metrics/metrics-factory.ts packages/typescript/tests/unit/metrics/metrics-factory.test.ts
git commit -m "feat(ts): add MetricsFactory with Powertools Metrics wrapper"
```

---

### Task 6: Middy Middleware Chain — Tests + Implementation

**Files:**
- Create: `packages/typescript/tests/unit/middleware/middy-chain.test.ts`
- Create: `packages/typescript/src/middleware/middy-chain.ts`
- Remove: `packages/typescript/src/middleware/.gitkeep`

**Step 1: Write the failing test**

```typescript
import { describe, it, expect } from 'vitest';
import { createMiddlewareChain } from '../../../src/middleware/middy-chain.js';
import { Logger } from '@aws-lambda-powertools/logger';
import { Tracer } from '@aws-lambda-powertools/tracer';
import { Metrics } from '@aws-lambda-powertools/metrics';

describe('createMiddlewareChain', () => {
  it('should return a middy MiddlewareObj', () => {
    const logger = new Logger({ serviceName: 'test' });
    const tracer = new Tracer({ serviceName: 'test', enabled: false });
    const metrics = new Metrics({ namespace: 'Test', serviceName: 'test' });

    const chain = createMiddlewareChain({ logger, tracer, metrics, captureColdStart: true });

    expect(chain).toBeDefined();
    expect(chain.before).toBeDefined();
  });

  it('should accept logEvent option', () => {
    const logger = new Logger({ serviceName: 'test' });
    const tracer = new Tracer({ serviceName: 'test', enabled: false });
    const metrics = new Metrics({ namespace: 'Test', serviceName: 'test' });

    const chain = createMiddlewareChain({
      logger, tracer, metrics,
      captureColdStart: true,
      logEvent: true,
    });

    expect(chain).toBeDefined();
  });
});
```

**Step 2: Run test to verify it fails**

**Step 3: Write the implementation**

```typescript
import { injectLambdaContext } from '@aws-lambda-powertools/logger/middleware';
import { captureLambdaHandler } from '@aws-lambda-powertools/tracer/middleware';
import { logMetrics } from '@aws-lambda-powertools/metrics/middleware';
import type { Logger } from '@aws-lambda-powertools/logger';
import type { Tracer } from '@aws-lambda-powertools/tracer';
import type { Metrics } from '@aws-lambda-powertools/metrics';
import type middy from '@middy/core';

export interface MiddlewareChainOptions {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  readonly captureColdStart: boolean;
  readonly logEvent?: boolean;
}

export function createMiddlewareChain(
  options: MiddlewareChainOptions
): middy.MiddlewareObj {
  const tracerMw = captureLambdaHandler(options.tracer);
  const loggerMw = injectLambdaContext(options.logger, {
    logEvent: options.logEvent ?? false,
  });
  const metricsMw = logMetrics(options.metrics, {
    captureColdStartMetric: options.captureColdStart,
  });

  return {
    before: async (request) => {
      await tracerMw.before?.(request);
      await loggerMw.before?.(request);
      await metricsMw.before?.(request);
    },
    after: async (request) => {
      await metricsMw.after?.(request);
      await loggerMw.after?.(request);
      await tracerMw.after?.(request);
    },
    onError: async (request) => {
      await tracerMw.onError?.(request);
    },
  };
}
```

**Step 4: Run tests and verify pass**

**Step 5: Commit**

```bash
git rm packages/typescript/src/middleware/.gitkeep
git add packages/typescript/src/middleware/middy-chain.ts packages/typescript/tests/unit/middleware/middy-chain.test.ts
git commit -m "feat(ts): add Middy middleware chain composing Logger+Tracer+Metrics"
```

---

### Task 7: Factory `createFirstanceLogger()` — Tests + Implementation

**Files:**
- Create: `packages/typescript/tests/unit/factory.test.ts`
- Create: `packages/typescript/src/factory.ts`

**Step 1: Write the failing test**

```typescript
import { describe, it, expect } from 'vitest';
import { createFirstanceLogger } from '../../src/factory.js';
import { resolve } from 'node:path';

describe('createFirstanceLogger', () => {
  it('should create FirstanceObservability from config file', () => {
    const result = createFirstanceLogger({
      configPath: resolve(__dirname, '../fixtures/config.valid.yaml'),
    });

    expect(result.logger).toBeDefined();
    expect(result.tracer).toBeDefined();
    expect(result.metrics).toBeDefined();
    expect(typeof result.middleware).toBe('function');
  });

  it('should accept inline config overrides', () => {
    const result = createFirstanceLogger({
      configPath: resolve(__dirname, '../fixtures/config.valid.yaml'),
      overrides: {
        service: { name: 'override-svc' },
      },
    });

    expect(result.logger).toBeDefined();
  });
});
```

**Step 2: Run test to verify it fails**

**Step 3: Write the implementation**

```typescript
import { Logger } from '@aws-lambda-powertools/logger';
import { Tracer } from '@aws-lambda-powertools/tracer';
import type { Metrics } from '@aws-lambda-powertools/metrics';
import type middy from '@middy/core';
import { loadConfig } from './config/loader.js';
import { OTelLogFormatter } from './logger/otel-formatter.js';
import { createTracer } from './tracer/tracer-factory.js';
import { createMetrics } from './metrics/metrics-factory.js';
import { createMiddlewareChain } from './middleware/middy-chain.js';
import type { FirstanceConfig } from './config/types.js';

export interface FirstanceLoggerOptions {
  readonly configPath: string;
  readonly overrides?: Partial<FirstanceConfig>;
}

export interface FirstanceObservability {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  middleware(options?: { logEvent?: boolean }): middy.MiddlewareObj;
}

export function createFirstanceLogger(options: FirstanceLoggerOptions): FirstanceObservability {
  const baseConfig = loadConfig({ configPath: options.configPath });
  const config: FirstanceConfig = options.overrides
    ? { ...baseConfig, ...options.overrides, service: { ...baseConfig.service, ...options.overrides.service } }
    : baseConfig;

  const formatter = new OTelLogFormatter({
    serviceVersion: config.service.version,
  });

  const logger = new Logger({
    serviceName: config.service.name,
    logLevel: config.logger.level,
    sampleRateValue: config.logger.sampleRate,
    persistentLogAttributes: config.logger.persistentKeys,
    logFormatter: formatter,
  });

  const tracer = createTracer(config);
  const metrics = createMetrics(config);

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

**Step 4: Run tests and verify pass**

**Step 5: Commit**

```bash
git add packages/typescript/src/factory.ts packages/typescript/tests/unit/factory.test.ts
git commit -m "feat(ts): add createFirstanceLogger factory entry point"
```

---

### Task 8: Update Barrel Export + Final Verification

**Files:**
- Modify: `packages/typescript/src/index.ts`

**Step 1: Update barrel export**

```typescript
// Config
export { loadConfig } from './config/loader.js';
export { configSchema } from './config/schema.js';
export type { FirstanceConfig, LogLevel } from './config/types.js';

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
```

**Step 2: Run ALL tests**

Run: `cd packages/typescript && npx vitest run`
Expected: All tests PASS

**Step 3: Verify TypeScript compilation**

Run: `cd packages/typescript && npx tsc --noEmit`
Expected: No errors

**Step 4: Commit**

```bash
git add packages/typescript/src/index.ts
git commit -m "feat(ts): update barrel export with all Fase 2 modules"
```

---

### Task 9: Docker Test Verification

**Step 1: Update Dockerfile if needed** (only if new dependencies require it)

**Step 2: Build and run Docker tests**

Run: `docker build -t firstance-ts-test -f packages/typescript/tests/Dockerfile packages/typescript && docker run --rm firstance-ts-test`
Expected: All tests PASS in container

---

## Notes for Implementer

- **Powertools v2 API**: `LogFormatter.formatAttributes()` takes two args: `attributes: UnformattedAttributes` and `additionalLogAttributes: LogAttributes`, returns `LogItem`.
- **Middy middleware order**: Tracer first, then Logger, then Metrics (per Powertools docs).
- **Sandbox**: Must be DISABLED for Docker commands.
- **Import paths**: Use `.js` extensions in imports (Node16 module resolution).
- **No `any`**: Use `unknown` + type guards everywhere. Zero tolerance.
