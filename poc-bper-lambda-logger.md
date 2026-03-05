# PoC — BPER Lambda Logger (PHP + TypeScript)

## Cambio di prospettiva

Powertools **non esiste per PHP**. Quindi il layer comune non è Powertools, ma **OpenTelemetry come protocollo di output**.

Il progetto diventa **due pacchetti gemelli** con la stessa interfaccia concettuale:

| | TypeScript (npm) | PHP (composer) |
|---|---|---|
| **Pacchetto** | `@bper/lambda-obs` | `bper/lambda-obs` |
| **Logger** | Powertools Logger | Monolog 3 |
| **OTel bridge** | Custom LogFormatter | `open-telemetry/opentelemetry-logger-monolog` |
| **Tracing** | Powertools Tracer (X-Ray) | OpenTelemetry PHP SDK + X-Ray |
| **Metrics** | Powertools Metrics (EMF) | CloudWatch EMF manuale o `aws/aws-sdk-php` |
| **Middleware** | Middy | Symfony HttpKernel EventSubscriber |
| **Config** | js-yaml + zod | symfony/yaml + validazione custom |
| **Config files** | **Stesso** `config.yaml` + `.env` | **Stesso** `config.yaml` + `.env` |

---

## Architettura unificata

```
┌─────────────────────────────────────────────────────────────┐
│                    CONFIGURAZIONE CONDIVISA                  │
│                                                             │
│  config.yaml  ←── struttura progetto (service, log level)   │
│  .env         ←── secrets, environment overrides            │
│                                                             │
│  STESSA STRUTTURA per entrambi i linguaggi                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
          ┌───────────┴───────────┐
          ▼                       ▼
┌──────────────────┐    ┌──────────────────────────────┐
│  TypeScript       │    │  PHP (Symfony)                │
│  Lambda           │    │  Lambda                      │
│                   │    │                              │
│  @bper/lambda-obs │    │  bper/lambda-obs             │
│  ┌──────────────┐ │    │  ┌────────────────────────┐  │
│  │ Powertools   │ │    │  │ Monolog 3              │  │
│  │ Logger       │ │    │  │ + OTel Monolog Handler │  │
│  │ + OTel       │ │    │  │                        │  │
│  │   Formatter  │ │    │  │ OpenTelemetry PHP SDK  │  │
│  ├──────────────┤ │    │  ├────────────────────────┤  │
│  │ Powertools   │ │    │  │ OTel Tracer            │  │
│  │ Tracer       │ │    │  │ + X-Ray propagation    │  │
│  ├──────────────┤ │    │  ├────────────────────────┤  │
│  │ Powertools   │ │    │  │ CloudWatch EMF         │  │
│  │ Metrics      │ │    │  │ (metrics manuali)      │  │
│  ├──────────────┤ │    │  ├────────────────────────┤  │
│  │ Middy        │ │    │  │ Symfony Kernel         │  │
│  │ middleware   │ │    │  │ EventSubscriber        │  │
│  └──────────────┘ │    │  └────────────────────────┘  │
└────────┬─────────┘    └──────────┬───────────────────┘
         │                         │
         │   STESSO OUTPUT FORMAT  │
         ▼                         ▼
┌────────────────────────────────────────────────────────────┐
│                    CloudWatch Logs                          │
│                                                            │
│  Formato OTel-compatibile (JSON strutturato)               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ {                                                    │  │
│  │   "Timestamp": "2025-03-05T10:30:00.000Z",          │  │
│  │   "SeverityText": "INFO",                           │  │
│  │   "Body": "Polizza elaborata",                      │  │
│  │   "Resource": {                                     │  │
│  │     "service.name": "bper-file-delivery",           │  │
│  │     "service.language": "php|typescript",           │  │
│  │     "faas.name": "processPolizza"                   │  │
│  │   },                                                │  │
│  │   "Attributes": {                                   │  │
│  │     "polizzaId": "POL-2025-001",                    │  │
│  │     "partner": "athora",                            │  │
│  │     "cold_start": false                             │  │
│  │   },                                                │  │
│  │   "TraceId": "1-abc123-def456"                      │  │
│  │ }                                                   │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                            │
│  → CloudWatch Logs Insights: query unificate PHP + TS      │
│  → X-Ray: trace cross-language se Lambda si invocano       │
└────────────────────────────────────────────────────────────┘
```

---

## Dipendenze PHP (composer.json)

```json
{
  "require": {
    "php": "^8.2",
    "monolog/monolog": "^3.0",
    "open-telemetry/opentelemetry-logger-monolog": "^1.0",
    "open-telemetry/sdk": "^1.0",
    "open-telemetry/exporter-otlp": "^1.0",
    "symfony/yaml": "^6.0|^7.0",
    "vlucas/phpdotenv": "^5.5"
  }
}
```

---

## Dipendenze TypeScript (package.json)

```json
{
  "dependencies": {
    "@aws-lambda-powertools/logger": "^2.31",
    "@aws-lambda-powertools/tracer": "^2.31",
    "@aws-lambda-powertools/metrics": "^2.31",
    "@middy/core": "^5.0",
    "js-yaml": "^4.1",
    "zod": "^3.23",
    "dotenv": "^16.4"
  }
}
```

---

## Config condivisa: `config.yaml`

```yaml
# Identico per PHP e TypeScript
service:
  name: "bper-file-delivery"
  version: "1.2.0"

logger:
  level: "INFO"                    # override da .env: POWERTOOLS_LOG_LEVEL
  sampleRate: 0.1
  persistentKeys:
    team: "integrations"
    partner: "athora"

tracer:
  enabled: true
  captureHTTPS: true

metrics:
  namespace: "BPERFileDelivery"
  captureColdStart: true
```

---

## Esempio d'uso — stesso pattern in entrambi i linguaggi

### TypeScript
```typescript
import { createBperLogger } from '@bper/lambda-obs';
import middy from '@middy/core';

const obs = createBperLogger({ configPath: './config.yaml' });

const lambdaHandler = async (event: any) => {
    obs.logger.info('Polizza elaborata', { polizzaId: 'POL-001' });
    obs.logger.error('Errore ISV', new Error('timeout'));
    return { statusCode: 200 };
};

export const handler = middy(lambdaHandler).use(obs.middleware());
```

### PHP
```php
use Bper\LambdaObs\BperLoggerFactory;

$obs = BperLoggerFactory::create(configPath: './config.yaml');

// In un Symfony Command o Lambda handler:
$obs->logger->info('Polizza elaborata', ['polizzaId' => 'POL-001']);
$obs->logger->error('Errore ISV', ['exception' => $e]);
```

> L'API per il developer è identica nella sostanza:
> `obs.logger.info(message, context)` (TS) = `$obs->logger->info(message, context)` (PHP)

---

## Struttura dei due pacchetti

```
bper-lambda-obs/
├── packages/
│   ├── typescript/                    # @bper/lambda-obs (npm)
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── config/
│   │   │   │   ├── loader.ts
│   │   │   │   └── schema.ts
│   │   │   ├── logger/
│   │   │   │   └── otel-formatter.ts
│   │   │   ├── factory.ts
│   │   │   └── middleware.ts
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   └── php/                           # bper/lambda-obs (composer)
│       ├── src/
│       │   ├── BperLoggerFactory.php
│       │   ├── Config/
│       │   │   ├── ConfigLoader.php
│       │   │   └── ConfigSchema.php
│       │   ├── Logger/
│       │   │   └── OTelCloudWatchFormatter.php
│       │   └── Middleware/
│       │       └── LambdaObsSubscriber.php
│       ├── composer.json
│       └── phpunit.xml
│
├── shared/
│   └── config.example.yaml            # Template condiviso
│
└── README.md
```

**Monorepo** con due pacchetti pubblicati separatamente (npm + Packagist).

---

## Criticità specifiche PHP

| # | Criticità | Mitigazione |
|---|---|---|
| 1 | **OTel PHP SDK è più pesante** di Powertools TS | Usare solo Logs + Traces, escludere Metrics SDK |
| 2 | **Cold start PHP Lambda** già alto (Bref/custom runtime) | Lambda Layer con estensione OTel precompilata |
| 3 | **No Middy equivalent** in PHP | EventSubscriber Symfony o decorator pattern |
| 4 | **EMF Metrics** non ha lib PHP ufficiale | Scrivere JSON EMF manualmente su stdout (è un formato semplice) |
| 5 | **X-Ray trace propagation** PHP ↔ TS | Header `X-Amzn-Trace-Id` è language-agnostic |

---

## Prossimi passi

1. **Fase 1 — TypeScript** (più semplice, Powertools fa quasi tutto)
   - Factory + OTelFormatter + Middy chain
   - Test su una Lambda esistente

2. **Fase 2 — PHP** (più lavoro manuale)
   - ConfigLoader (stessa logica, symfony/yaml + phpdotenv)
   - OTelCloudWatchFormatter (Monolog Formatter → JSON OTel)
   - Integrazione OTel Monolog Handler
   - Test su una Lambda Symfony esistente

3. **Fase 3 — Validazione cross-language**
   - CloudWatch Logs Insights: query che funzionano su entrambi
   - X-Ray: trace che attraversano Lambda PHP → Lambda TS
   - Dashboard unificata

---

## Query CloudWatch Logs Insights (funziona su entrambi)

```
fields @timestamp, Resource.`service.name`, SeverityText, Body, Attributes.polizzaId
| filter Resource.`service.name` = "bper-file-delivery"
| filter SeverityText = "ERROR"
| sort @timestamp desc
| limit 50
```

Questa query restituisce errori da TUTTE le Lambda (PHP e TS) perché
il formato di output è identico.
