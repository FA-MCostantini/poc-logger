# Fase 0+1 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Set up the monorepo structure and implement the shared config layer (JSON Schema, YAML loader, env override) for both TypeScript and PHP packages.

**Architecture:** Monorepo with two packages under `packages/`. Shared JSON Schema defines the config structure. Each package has its own ConfigLoader that reads `config.yaml`, merges `.env` overrides, and validates against the schema. Config is exposed as a typed readonly object in both languages.

**Tech Stack:** TypeScript (Zod v3, js-yaml, dotenv, Vitest), PHP 8.2 (symfony/yaml, vlucas/phpdotenv, PHPUnit 11), Docker for test execution.

---

## Fase 0 — Setup struttura progetto

### Task 1: Create monorepo directory structure

**Files:**
- Create: all directories listed below

**Step 1: Create directory tree**

Run:
```bash
cd /home/rancor/PhpstormProjects/cProject/logger && \
mkdir -p packages/typescript/src/config && \
mkdir -p packages/typescript/src/logger && \
mkdir -p packages/typescript/src/tracer && \
mkdir -p packages/typescript/src/metrics && \
mkdir -p packages/typescript/src/middleware && \
mkdir -p packages/typescript/tests/unit/config && \
mkdir -p packages/typescript/tests/unit/logger && \
mkdir -p packages/typescript/tests/unit/tracer && \
mkdir -p packages/typescript/tests/unit/metrics && \
mkdir -p packages/typescript/tests/integration && \
mkdir -p packages/typescript/tests/fixtures && \
mkdir -p packages/typescript/tests/helpers && \
mkdir -p packages/php/src/Config && \
mkdir -p packages/php/src/Logger && \
mkdir -p packages/php/src/Tracer && \
mkdir -p packages/php/src/Metrics && \
mkdir -p packages/php/src/Middleware && \
mkdir -p packages/php/tests/Unit/Config && \
mkdir -p packages/php/tests/Unit/Logger && \
mkdir -p packages/php/tests/Unit/Tracer && \
mkdir -p packages/php/tests/Unit/Metrics && \
mkdir -p packages/php/tests/Integration && \
mkdir -p packages/php/tests/Fixtures && \
mkdir -p packages/php/tests/Helpers && \
mkdir -p shared/schemas && \
mkdir -p tests && \
mkdir -p docs
```

**Step 2: Verify structure**

Run: `find /home/rancor/PhpstormProjects/cProject/logger -type d | grep -v .git | sort`
Expected: all directories listed above

---

### Task 2: Create root .gitignore

**Files:**
- Modify: `.gitignore`

**Step 1: Update .gitignore**

```gitignore
# Ambiente e credenziali
.env

# Dipendenze
vendor/
node_modules/

# Lock files
composer.lock
package-lock.json

# Build output
dist/
packages/typescript/dist/

# Test
tests/logs/
coverage/
.phpunit.result.cache

# Claude Code
.claude/

# Log batch
logs/

# PhPStorm
.idea/

# OS
*.Zone.Identifier
.DS_Store
```

**Step 2: Commit**

```bash
git add -A && git commit -m "chore: create monorepo directory structure"
```

---

### Task 3: Create TypeScript package.json and tsconfig

**Files:**
- Create: `packages/typescript/package.json`
- Create: `packages/typescript/tsconfig.json`
- Create: `packages/typescript/tsconfig.build.json`

**Step 1: Create package.json**

```json
{
  "name": "@firstance/lambda-obs",
  "version": "1.0.0",
  "description": "Observability unificata per AWS Lambda — Logger, Tracer, Metrics con output OTel",
  "main": "dist/index.js",
  "types": "dist/index.d.ts",
  "private": true,
  "scripts": {
    "build": "tsc -p tsconfig.build.json",
    "test": "vitest run",
    "test:watch": "vitest",
    "test:coverage": "vitest run --coverage",
    "lint": "eslint src/ tests/"
  },
  "dependencies": {
    "@aws-lambda-powertools/logger": "^2.31.0",
    "@aws-lambda-powertools/tracer": "^2.31.0",
    "@aws-lambda-powertools/metrics": "^2.31.0",
    "@middy/core": "^5.0.0",
    "js-yaml": "^4.1.0",
    "zod": "^3.23.0",
    "dotenv": "^16.4.0"
  },
  "devDependencies": {
    "@types/js-yaml": "^4.0.9",
    "@types/node": "^20.0.0",
    "typescript": "^5.4.0",
    "vitest": "^3.0.0"
  },
  "engines": {
    "node": ">=20.0.0"
  }
}
```

**Step 2: Create tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "Node16",
    "moduleResolution": "Node16",
    "lib": ["ES2022"],
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "resolveJsonModule": true,
    "declaration": true,
    "declarationMap": true,
    "sourceMap": true,
    "outDir": "./dist",
    "rootDir": "./src",
    "noUncheckedIndexedAccess": true,
    "exactOptionalPropertyTypes": false
  },
  "include": ["src/**/*.ts"],
  "exclude": ["node_modules", "dist", "tests"]
}
```

**Step 3: Create tsconfig.build.json**

```json
{
  "extends": "./tsconfig.json",
  "exclude": ["node_modules", "dist", "tests", "**/*.test.ts"]
}
```

---

### Task 4: Create Vitest config

**Files:**
- Create: `packages/typescript/vitest.config.ts`

**Step 1: Create vitest.config.ts**

```typescript
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    root: '.',
    include: ['tests/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.ts'],
      exclude: ['src/index.ts'],
      thresholds: {
        statements: 90,
        branches: 85,
      },
    },
  },
});
```

---

### Task 5: Create PHP composer.json

**Files:**
- Create: `packages/php/composer.json`

**Step 1: Create composer.json**

```json
{
  "name": "firstance/lambda-obs",
  "description": "Observability unificata per AWS Lambda PHP — Logger, Tracer, Metrics con output OTel",
  "type": "library",
  "license": "MIT",
  "minimum-stability": "stable",
  "require": {
    "php": "^8.2",
    "monolog/monolog": "^3.0",
    "open-telemetry/opentelemetry-logger-monolog": "^1.0",
    "open-telemetry/sdk": "^1.0",
    "open-telemetry/exporter-otlp": "^1.0",
    "symfony/yaml": "^6.0|^7.0",
    "vlucas/phpdotenv": "^5.5"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "phpstan/phpstan": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "Firstance\\LambdaObs\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Firstance\\LambdaObs\\Tests\\": "tests/"
    }
  }
}
```

---

### Task 6: Create PHPUnit config

**Files:**
- Create: `packages/php/phpunit.xml`

**Step 1: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         requireCoverageMetadata="false"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

---

### Task 7: Create PHPStan config

**Files:**
- Create: `packages/php/phpstan.neon`

**Step 1: Create phpstan.neon**

```neon
parameters:
    level: 8
    paths:
        - src
    tmpDir: .phpstan.cache
```

---

### Task 8: Create Docker test files

**Files:**
- Create: `packages/typescript/tests/Dockerfile`
- Create: `packages/php/tests/Dockerfile`

**Step 1: Create TypeScript test Dockerfile**

```dockerfile
FROM node:20-alpine

WORKDIR /app

COPY package.json ./
RUN npm install

COPY tsconfig.json vitest.config.ts ./
COPY src/ ./src/
COPY tests/ ./tests/

CMD ["npx", "vitest", "run", "--reporter=verbose"]
```

**Step 2: Create PHP test Dockerfile**

```dockerfile
FROM php:8.2-cli-alpine

RUN apk add --no-cache unzip curl && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-interaction --no-progress

COPY phpunit.xml phpstan.neon ./
COPY src/ ./src/
COPY tests/ ./tests/

CMD ["vendor/bin/phpunit", "--testdox"]
```

**Step 3: Commit**

```bash
git add -A && git commit -m "chore: add package configs, Dockerfiles, and tool configs"
```

---

## Fase 1 — Config Layer

### Task 9: Create shared JSON Schema for config.yaml

**Files:**
- Create: `shared/schemas/config-schema.json`

**Step 1: Create the JSON Schema**

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://firstance.it/lambda-obs/config-schema.json",
  "title": "Firstance Lambda Obs Configuration",
  "type": "object",
  "required": ["service"],
  "properties": {
    "service": {
      "type": "object",
      "required": ["name"],
      "properties": {
        "name": {
          "type": "string",
          "minLength": 1,
          "description": "Service name for OTel Resource"
        },
        "version": {
          "type": "string",
          "default": "0.0.0",
          "description": "Service version (semver)"
        }
      },
      "additionalProperties": false
    },
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

**Step 2: Commit**

```bash
git add shared/schemas/config-schema.json && git commit -m "feat: add shared JSON Schema for config.yaml"
```

---

### Task 10: Create shared config.example.yaml

**Files:**
- Create: `shared/config.example.yaml`

**Step 1: Create template**

```yaml
# Firstance Lambda Obs — Configurazione condivisa
# Identico per PHP e TypeScript
#
# Env overrides (12-factor):
#   POWERTOOLS_LOG_LEVEL  -> logger.level
#   POWERTOOLS_SERVICE_NAME -> service.name
#   Firstance_OBS_SAMPLE_RATE -> logger.sampleRate
#   Firstance_OBS_METRICS_NAMESPACE -> metrics.namespace

service:
  name: "firstance-file-delivery"
  version: "1.0.0"

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

**Step 2: Commit**

```bash
git add shared/config.example.yaml && git commit -m "feat: add shared config.example.yaml template"
```

---

### Task 11: TypeScript — Zod schema + types

**Files:**
- Create: `packages/typescript/src/config/types.ts`
- Create: `packages/typescript/src/config/schema.ts`
- Test: `packages/typescript/tests/unit/config/schema.test.ts`

**Step 1: Write the failing test**

```typescript
// packages/typescript/tests/unit/config/schema.test.ts
import { describe, it, expect } from 'vitest';
import { configSchema } from '../../../src/config/schema.js';

describe('configSchema', () => {
  it('should parse a valid full config', () => {
    const input = {
      service: { name: 'my-service', version: '1.0.0' },
      logger: { level: 'INFO', sampleRate: 0.5, persistentKeys: { team: 'a' } },
      tracer: { enabled: true, captureHTTPS: false },
      metrics: { namespace: 'MyNS', captureColdStart: true },
    };
    const result = configSchema.parse(input);
    expect(result.service.name).toBe('my-service');
    expect(result.logger.level).toBe('INFO');
    expect(result.tracer.captureHTTPS).toBe(false);
  });

  it('should apply defaults for optional sections', () => {
    const input = { service: { name: 'minimal' } };
    const result = configSchema.parse(input);
    expect(result.service.version).toBe('0.0.0');
    expect(result.logger.level).toBe('INFO');
    expect(result.logger.sampleRate).toBe(1.0);
    expect(result.logger.persistentKeys).toEqual({});
    expect(result.tracer.enabled).toBe(true);
    expect(result.metrics.namespace).toBe('Default');
  });

  it('should reject config without service.name', () => {
    expect(() => configSchema.parse({})).toThrow();
    expect(() => configSchema.parse({ service: {} })).toThrow();
  });

  it('should reject invalid log level', () => {
    const input = { service: { name: 's' }, logger: { level: 'TRACE' } };
    expect(() => configSchema.parse(input)).toThrow();
  });

  it('should reject sampleRate out of range', () => {
    expect(() =>
      configSchema.parse({ service: { name: 's' }, logger: { sampleRate: 1.5 } })
    ).toThrow();
    expect(() =>
      configSchema.parse({ service: { name: 's' }, logger: { sampleRate: -0.1 } })
    ).toThrow();
  });
});
```

**Step 2: Run test to verify it fails**

Run (in Docker):
```bash
cd packages/typescript && \
docker build -f tests/Dockerfile -t firstance-ts-test . && \
docker run --rm firstance-ts-test npx vitest run tests/unit/config/schema.test.ts
```
Expected: FAIL — module not found

**Step 3: Create types.ts**

```typescript
// packages/typescript/src/config/types.ts
import type { z } from 'zod';
import type { configSchema } from './schema.js';

export type FirstanceConfig = z.infer<typeof configSchema>;

export type LogLevel = 'DEBUG' | 'INFO' | 'WARN' | 'ERROR';

export const ENV_MAPPINGS = {
  'POWERTOOLS_LOG_LEVEL': 'logger.level',
  'POWERTOOLS_SERVICE_NAME': 'service.name',
  'Firstance_OBS_SAMPLE_RATE': 'logger.sampleRate',
  'Firstance_OBS_METRICS_NAMESPACE': 'metrics.namespace',
} as const;

export type EnvKey = keyof typeof ENV_MAPPINGS;
```

**Step 4: Create schema.ts**

```typescript
// packages/typescript/src/config/schema.ts
import { z } from 'zod';

const logLevelSchema = z.enum(['DEBUG', 'INFO', 'WARN', 'ERROR']);

const serviceSchema = z.object({
  name: z.string().min(1),
  version: z.string().default('0.0.0'),
});

const loggerSchema = z.object({
  level: logLevelSchema.default('INFO'),
  sampleRate: z.number().min(0).max(1).default(1.0),
  persistentKeys: z.record(z.string()).default({}),
}).default({});

const tracerSchema = z.object({
  enabled: z.boolean().default(true),
  captureHTTPS: z.boolean().default(true),
}).default({});

const metricsSchema = z.object({
  namespace: z.string().default('Default'),
  captureColdStart: z.boolean().default(true),
}).default({});

export const configSchema = z.object({
  service: serviceSchema,
  logger: loggerSchema,
  tracer: tracerSchema,
  metrics: metricsSchema,
});
```

**Step 5: Run test to verify it passes**

Run (in Docker): same docker build + run command as Step 2
Expected: PASS — all 5 tests green

**Step 6: Commit**

```bash
git add packages/typescript/src/config/schema.ts packages/typescript/src/config/types.ts packages/typescript/tests/unit/config/schema.test.ts && \
git commit -m "feat(ts): add Zod config schema with validation and defaults"
```

---

### Task 12: TypeScript — ConfigLoader (YAML + env merge)

**Files:**
- Create: `packages/typescript/src/config/loader.ts`
- Test: `packages/typescript/tests/unit/config/loader.test.ts`
- Create: `packages/typescript/tests/fixtures/config.valid.yaml`
- Create: `packages/typescript/tests/fixtures/config.minimal.yaml`
- Create: `packages/typescript/tests/fixtures/config.invalid.yaml`
- Create: `packages/typescript/tests/fixtures/.env.test`

**Step 1: Create test fixtures**

```yaml
# packages/typescript/tests/fixtures/config.valid.yaml
service:
  name: "test-service"
  version: "2.0.0"

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

```yaml
# packages/typescript/tests/fixtures/config.minimal.yaml
service:
  name: "minimal-service"
```

```yaml
# packages/typescript/tests/fixtures/config.invalid.yaml
logger:
  level: "INVALID_LEVEL"
```

```dotenv
# packages/typescript/tests/fixtures/.env.test
POWERTOOLS_LOG_LEVEL=ERROR
POWERTOOLS_SERVICE_NAME=env-override-service
Firstance_OBS_SAMPLE_RATE=0.75
Firstance_OBS_METRICS_NAMESPACE=EnvNamespace
```

**Step 2: Write the failing test**

```typescript
// packages/typescript/tests/unit/config/loader.test.ts
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import path from 'node:path';
import { loadConfig } from '../../../src/config/loader.js';

const FIXTURES = path.resolve(__dirname, '../../../tests/fixtures');

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

  it('should throw on missing YAML file', () => {
    expect(() =>
      loadConfig({ configPath: path.join(FIXTURES, 'nonexistent.yaml') })
    ).toThrow();
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
    // YAML says DEBUG, env says WARN — env wins
    expect(config.logger.level).toBe('WARN');
    // Non-overridden values stay from YAML
    expect(config.service.name).toBe('test-service');
  });
});
```

**Step 3: Run test to verify it fails**

Run (in Docker): build + run
Expected: FAIL — module `loader` not found

**Step 4: Write minimal implementation**

```typescript
// packages/typescript/src/config/loader.ts
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
```

**Step 5: Run test to verify it passes**

Run (in Docker): build + run
Expected: PASS — all 6 tests green

**Step 6: Commit**

```bash
git add packages/typescript/src/config/loader.ts packages/typescript/tests/unit/config/loader.test.ts packages/typescript/tests/fixtures/ && \
git commit -m "feat(ts): add ConfigLoader with YAML parsing and env override"
```

---

### Task 13: PHP — ConfigDTO (readonly value object)

**Files:**
- Create: `packages/php/src/Config/ConfigDTO.php`
- Test: `packages/php/tests/Unit/Config/ConfigDTOTest.php`

**Step 1: Write the failing test**

```php
<?php
// packages/php/tests/Unit/Config/ConfigDTOTest.php
declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ConfigDTO;
use PHPUnit\Framework\TestCase;

final class ConfigDTOTest extends TestCase
{
    public function testCreatesWithAllValues(): void
    {
        $dto = new ConfigDTO(
            serviceName: 'my-service',
            serviceVersion: '1.0.0',
            logLevel: 'DEBUG',
            logSampleRate: 0.5,
            persistentKeys: ['team' => 'integrations'],
            tracerEnabled: true,
            tracerCaptureHTTPS: false,
            metricsNamespace: 'MyNS',
            metricsCaptureColdStart: true,
        );

        $this->assertSame('my-service', $dto->serviceName);
        $this->assertSame('1.0.0', $dto->serviceVersion);
        $this->assertSame('DEBUG', $dto->logLevel);
        $this->assertSame(0.5, $dto->logSampleRate);
        $this->assertSame(['team' => 'integrations'], $dto->persistentKeys);
        $this->assertTrue($dto->tracerEnabled);
        $this->assertFalse($dto->tracerCaptureHTTPS);
        $this->assertSame('MyNS', $dto->metricsNamespace);
        $this->assertTrue($dto->metricsCaptureColdStart);
    }

    public function testCreatesWithDefaults(): void
    {
        $dto = new ConfigDTO(serviceName: 'minimal');

        $this->assertSame('minimal', $dto->serviceName);
        $this->assertSame('0.0.0', $dto->serviceVersion);
        $this->assertSame('INFO', $dto->logLevel);
        $this->assertSame(1.0, $dto->logSampleRate);
        $this->assertSame([], $dto->persistentKeys);
        $this->assertTrue($dto->tracerEnabled);
        $this->assertTrue($dto->tracerCaptureHTTPS);
        $this->assertSame('Default', $dto->metricsNamespace);
        $this->assertTrue($dto->metricsCaptureColdStart);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
cd packages/php && \
docker build -f tests/Dockerfile -t firstance-php-test . && \
docker run --rm firstance-php-test vendor/bin/phpunit --filter ConfigDTOTest
```
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php
// packages/php/src/Config/ConfigDTO.php
declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

final readonly class ConfigDTO
{
    /**
     * @param array<string, string> $persistentKeys
     */
    public function __construct(
        public string $serviceName,
        public string $serviceVersion = '0.0.0',
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

**Step 4: Run test to verify it passes**

Run: same docker command as Step 2
Expected: PASS — 2 tests green

**Step 5: Commit**

```bash
git add packages/php/src/Config/ConfigDTO.php packages/php/tests/Unit/Config/ConfigDTOTest.php && \
git commit -m "feat(php): add ConfigDTO readonly value object"
```

---

### Task 14: PHP — ConfigSchema (validation)

**Files:**
- Create: `packages/php/src/Config/ConfigSchema.php`
- Test: `packages/php/tests/Unit/Config/ConfigSchemaTest.php`

**Step 1: Write the failing test**

```php
<?php
// packages/php/tests/Unit/Config/ConfigSchemaTest.php
declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ConfigSchema;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaTest extends TestCase
{
    public function testValidatesFullConfig(): void
    {
        $raw = [
            'service' => ['name' => 'test', 'version' => '1.0.0'],
            'logger' => ['level' => 'DEBUG', 'sampleRate' => 0.5, 'persistentKeys' => ['k' => 'v']],
            'tracer' => ['enabled' => true, 'captureHTTPS' => false],
            'metrics' => ['namespace' => 'NS', 'captureColdStart' => true],
        ];

        $dto = ConfigSchema::validate($raw);

        $this->assertSame('test', $dto->serviceName);
        $this->assertSame('DEBUG', $dto->logLevel);
        $this->assertFalse($dto->tracerCaptureHTTPS);
    }

    public function testAppliesDefaults(): void
    {
        $raw = ['service' => ['name' => 'minimal']];

        $dto = ConfigSchema::validate($raw);

        $this->assertSame('0.0.0', $dto->serviceVersion);
        $this->assertSame('INFO', $dto->logLevel);
        $this->assertSame(1.0, $dto->logSampleRate);
    }

    public function testThrowsOnMissingServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('service.name');

        ConfigSchema::validate([]);
    }

    public function testThrowsOnEmptyServiceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigSchema::validate(['service' => ['name' => '']]);
    }

    public function testThrowsOnInvalidLogLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.level');

        ConfigSchema::validate(['service' => ['name' => 's'], 'logger' => ['level' => 'TRACE']]);
    }

    public function testThrowsOnSampleRateOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigSchema::validate(['service' => ['name' => 's'], 'logger' => ['sampleRate' => 1.5]]);
    }
}
```

**Step 2: Run test — expect FAIL**

**Step 3: Write implementation**

```php
<?php
// packages/php/src/Config/ConfigSchema.php
declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

final class ConfigSchema
{
    private const VALID_LOG_LEVELS = ['DEBUG', 'INFO', 'WARN', 'ERROR'];

    /**
     * @param array<string, mixed> $raw
     */
    public static function validate(array $raw): ConfigDTO
    {
        $service = self::arrayGet($raw, 'service', []);
        if (!is_array($service)) {
            throw new \InvalidArgumentException('service must be an object');
        }

        $serviceName = self::arrayGet($service, 'name', null);
        if (!is_string($serviceName) || $serviceName === '') {
            throw new \InvalidArgumentException('service.name is required and must be a non-empty string');
        }

        $serviceVersion = self::stringOrDefault($service, 'version', '0.0.0');

        $logger = is_array(self::arrayGet($raw, 'logger', [])) ? self::arrayGet($raw, 'logger', []) : [];
        $logLevel = self::stringOrDefault($logger, 'level', 'INFO');
        if (!in_array($logLevel, self::VALID_LOG_LEVELS, true)) {
            throw new \InvalidArgumentException(
                sprintf('logger.level must be one of %s, got "%s"', implode(', ', self::VALID_LOG_LEVELS), $logLevel)
            );
        }

        $sampleRate = self::floatOrDefault($logger, 'sampleRate', 1.0);
        if ($sampleRate < 0.0 || $sampleRate > 1.0) {
            throw new \InvalidArgumentException('logger.sampleRate must be between 0.0 and 1.0');
        }

        $persistentKeys = self::arrayGet($logger, 'persistentKeys', []);
        if (!is_array($persistentKeys)) {
            $persistentKeys = [];
        }

        $tracer = is_array(self::arrayGet($raw, 'tracer', [])) ? self::arrayGet($raw, 'tracer', []) : [];
        $tracerEnabled = self::boolOrDefault($tracer, 'enabled', true);
        $tracerCaptureHTTPS = self::boolOrDefault($tracer, 'captureHTTPS', true);

        $metrics = is_array(self::arrayGet($raw, 'metrics', [])) ? self::arrayGet($raw, 'metrics', []) : [];
        $metricsNamespace = self::stringOrDefault($metrics, 'namespace', 'Default');
        $metricsCaptureColdStart = self::boolOrDefault($metrics, 'captureColdStart', true);

        return new ConfigDTO(
            serviceName: $serviceName,
            serviceVersion: $serviceVersion,
            logLevel: $logLevel,
            logSampleRate: $sampleRate,
            persistentKeys: $persistentKeys,
            tracerEnabled: $tracerEnabled,
            tracerCaptureHTTPS: $tracerCaptureHTTPS,
            metricsNamespace: $metricsNamespace,
            metricsCaptureColdStart: $metricsCaptureColdStart,
        );
    }

    private static function arrayGet(array $array, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    private static function stringOrDefault(array $array, string $key, string $default): string
    {
        $value = self::arrayGet($array, $key, $default);
        return is_string($value) ? $value : $default;
    }

    private static function floatOrDefault(array $array, string $key, float $default): float
    {
        $value = self::arrayGet($array, $key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    private static function boolOrDefault(array $array, string $key, bool $default): bool
    {
        $value = self::arrayGet($array, $key, $default);
        return is_bool($value) ? $value : $default;
    }
}
```

**Step 4: Run test — expect PASS (6 tests)**

**Step 5: Commit**

```bash
git add packages/php/src/Config/ConfigSchema.php packages/php/tests/Unit/Config/ConfigSchemaTest.php && \
git commit -m "feat(php): add ConfigSchema with validation and defaults"
```

---

### Task 15: PHP — ConfigLoader (YAML + env merge)

**Files:**
- Create: `packages/php/src/Config/ConfigLoader.php`
- Test: `packages/php/tests/Unit/Config/ConfigLoaderTest.php`
- Create: `packages/php/tests/Fixtures/config.valid.yaml`
- Create: `packages/php/tests/Fixtures/config.minimal.yaml`
- Create: `packages/php/tests/Fixtures/config.invalid.yaml`
- Create: `packages/php/tests/Fixtures/.env.test`

**Step 1: Create test fixtures**

```yaml
# packages/php/tests/Fixtures/config.valid.yaml
service:
  name: "test-service"
  version: "2.0.0"

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

```yaml
# packages/php/tests/Fixtures/config.minimal.yaml
service:
  name: "minimal-service"
```

```yaml
# packages/php/tests/Fixtures/config.invalid.yaml
logger:
  level: "INVALID_LEVEL"
```

```dotenv
# packages/php/tests/Fixtures/.env.test
POWERTOOLS_LOG_LEVEL=ERROR
POWERTOOLS_SERVICE_NAME=env-override-service
Firstance_OBS_SAMPLE_RATE=0.75
Firstance_OBS_METRICS_NAMESPACE=EnvNamespace
```

**Step 2: Write the failing test**

```php
<?php
// packages/php/tests/Unit/Config/ConfigLoaderTest.php
declare(strict_types=1);

namespace Firstance\LambdaObs\Tests\Unit\Config;

use Firstance\LambdaObs\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__, 2) . '/Fixtures';
        // Clean env before each test
        putenv('POWERTOOLS_LOG_LEVEL');
        putenv('POWERTOOLS_SERVICE_NAME');
        putenv('Firstance_OBS_SAMPLE_RATE');
        putenv('Firstance_OBS_METRICS_NAMESPACE');
    }

    protected function tearDown(): void
    {
        putenv('POWERTOOLS_LOG_LEVEL');
        putenv('POWERTOOLS_SERVICE_NAME');
        putenv('Firstance_OBS_SAMPLE_RATE');
        putenv('Firstance_OBS_METRICS_NAMESPACE');
    }

    public function testLoadsValidFullConfig(): void
    {
        $config = ConfigLoader::load($this->fixturesPath . '/config.valid.yaml');

        $this->assertSame('test-service', $config->serviceName);
        $this->assertSame('2.0.0', $config->serviceVersion);
        $this->assertSame('DEBUG', $config->logLevel);
        $this->assertSame(0.5, $config->logSampleRate);
        $this->assertSame(['team' => 'test-team'], $config->persistentKeys);
        $this->assertFalse($config->tracerCaptureHTTPS);
        $this->assertSame('TestNS', $config->metricsNamespace);
    }

    public function testLoadsMinimalConfigWithDefaults(): void
    {
        $config = ConfigLoader::load($this->fixturesPath . '/config.minimal.yaml');

        $this->assertSame('minimal-service', $config->serviceName);
        $this->assertSame('0.0.0', $config->serviceVersion);
        $this->assertSame('INFO', $config->logLevel);
    }

    public function testThrowsOnInvalidConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigLoader::load($this->fixturesPath . '/config.invalid.yaml');
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        ConfigLoader::load($this->fixturesPath . '/nonexistent.yaml');
    }

    public function testEnvOverridesYamlValues(): void
    {
        putenv('POWERTOOLS_LOG_LEVEL=ERROR');
        putenv('POWERTOOLS_SERVICE_NAME=env-service');
        putenv('Firstance_OBS_SAMPLE_RATE=0.75');
        putenv('Firstance_OBS_METRICS_NAMESPACE=EnvNS');

        $config = ConfigLoader::load($this->fixturesPath . '/config.valid.yaml');

        $this->assertSame('env-service', $config->serviceName);
        $this->assertSame('ERROR', $config->logLevel);
        $this->assertSame(0.75, $config->logSampleRate);
        $this->assertSame('EnvNS', $config->metricsNamespace);
    }

    public function testEnvTakesPrecedenceOverYaml(): void
    {
        putenv('POWERTOOLS_LOG_LEVEL=WARN');

        $config = ConfigLoader::load($this->fixturesPath . '/config.valid.yaml');

        // YAML says DEBUG, env says WARN — env wins
        $this->assertSame('WARN', $config->logLevel);
        // Non-overridden stay from YAML
        $this->assertSame('test-service', $config->serviceName);
    }
}
```

**Step 3: Run test — expect FAIL**

**Step 4: Write implementation**

```php
<?php
// packages/php/src/Config/ConfigLoader.php
declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    private const ENV_MAPPINGS = [
        'POWERTOOLS_SERVICE_NAME' => ['service', 'name'],
        'POWERTOOLS_LOG_LEVEL' => ['logger', 'level'],
        'Firstance_OBS_SAMPLE_RATE' => ['logger', 'sampleRate'],
        'Firstance_OBS_METRICS_NAMESPACE' => ['metrics', 'namespace'],
    ];

    private const FLOAT_KEYS = ['Firstance_OBS_SAMPLE_RATE'];

    public static function load(string $configPath): ConfigDTO
    {
        $raw = self::readYaml($configPath);
        $merged = self::applyEnvOverrides($raw);

        return ConfigSchema::validate($merged);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readYaml(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException(sprintf('Config file not found: %s', $filePath));
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read config file: %s', $filePath));
        }

        $parsed = Yaml::parse($content);
        if (!is_array($parsed)) {
            throw new \RuntimeException(sprintf('Invalid YAML content in %s', $filePath));
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function applyEnvOverrides(array $config): array
    {
        foreach (self::ENV_MAPPINGS as $envKey => $path) {
            $envValue = getenv($envKey);
            if ($envValue === false) {
                continue;
            }

            $value = in_array($envKey, self::FLOAT_KEYS, true)
                ? (float) $envValue
                : $envValue;

            self::setNestedValue($config, $path, $value);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $path
     */
    private static function setNestedValue(array &$array, array $path, mixed $value): void
    {
        $current = &$array;
        for ($i = 0; $i < count($path) - 1; $i++) {
            $key = $path[$i];
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current[$path[count($path) - 1]] = $value;
    }
}
```

**Step 5: Run test — expect PASS (6 tests)**

**Step 6: Commit**

```bash
git add packages/php/src/Config/ConfigLoader.php packages/php/tests/Unit/Config/ConfigLoaderTest.php packages/php/tests/Fixtures/ && \
git commit -m "feat(php): add ConfigLoader with YAML parsing and env override"
```

---

### Task 16: TypeScript — barrel export for config module

**Files:**
- Create: `packages/typescript/src/index.ts`

**Step 1: Create index.ts**

```typescript
// packages/typescript/src/index.ts
export { loadConfig } from './config/loader.js';
export { configSchema } from './config/schema.js';
export type { FirstanceConfig, LogLevel } from './config/types.js';
```

**Step 2: Commit**

```bash
git add packages/typescript/src/index.ts && \
git commit -m "feat(ts): add barrel export for config module"
```

---

### Task 17: Run full test suites in Docker — gate check

**Step 1: Build and run TypeScript tests**

```bash
cd /home/rancor/PhpstormProjects/cProject/logger && \
cd packages/typescript && \
docker build -f tests/Dockerfile -t firstance-ts-test . && \
docker run --rm firstance-ts-test
```
Expected: all unit tests PASS

**Step 2: Build and run PHP tests**

```bash
cd /home/rancor/PhpstormProjects/cProject/logger && \
cd packages/php && \
docker build -f tests/Dockerfile -t firstance-php-test . && \
docker run --rm firstance-php-test
```
Expected: all unit tests PASS

**Step 3: Verify milestone 0+1 complete**

Gate criteria:
- [ ] Directory structure created
- [ ] JSON Schema defined
- [ ] config.example.yaml matches schema
- [ ] TS ConfigLoader: parses YAML, validates with Zod, applies env overrides
- [ ] PHP ConfigLoader: parses YAML, validates with ConfigSchema, applies env overrides
- [ ] Both loaders produce equivalent output for same input
- [ ] All tests pass in Docker containers

---

## Summary

| Task | Component | Language |
|------|-----------|----------|
| 1-2 | Directory structure + .gitignore | — |
| 3-4 | package.json + tsconfig + vitest | TS |
| 5-7 | composer.json + phpunit + phpstan | PHP |
| 8 | Test Dockerfiles | Docker |
| 9-10 | JSON Schema + config.example.yaml | Shared |
| 11 | Zod schema + types | TS |
| 12 | ConfigLoader | TS |
| 13 | ConfigDTO | PHP |
| 14 | ConfigSchema | PHP |
| 15 | ConfigLoader | PHP |
| 16 | Barrel export | TS |
| 17 | Docker gate check | Both |
