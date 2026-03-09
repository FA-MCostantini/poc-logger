# poc-logger

Libreria di observability unificata per AWS Lambda, con output OTel JSON compatibile con CloudWatch Logs Insights.
Disponibile in **TypeScript** (npm) e **PHP** (Composer). Entrambe le implementazioni producono strutture JSON identiche.

> Uso interno Firstance — Proof of Concept. Non pubblicato su npm o Packagist.

---

## Quick Start — TypeScript

### 1. Installa come dipendenza

```bash
npm install git+https://github.com/FA-MCostantini/poc-logger.git#v0.2.1
```

Oppure aggiungi manualmente al tuo `package.json`:

```json
{
  "dependencies": {
    "poc-logger": "github:FA-MCostantini/poc-logger#v0.2.1"
  }
}
```

> Le versioni seguono git tag semver (es. `v0.2.1`). Consulta i [rilasci](https://github.com/FA-MCostantini/poc-logger/tags) per la versione più recente.

> La compilazione TypeScript avviene automaticamente durante l'installazione tramite lo script `prepare`.

> Tutte le dipendenze necessarie (`@middy/core`, `@aws-lambda-powertools/*`, `@types/aws-lambda`) vengono installate automaticamente — non è necessario aggiungerle al tuo progetto.

### 2. Crea `config.yaml`

> `service.name` e `service.version` vengono auto-scoperti da `package.json` / `composer.json` — non servono nel config.

```yaml
logger:
  level: "INFO"
  sampleRate: 0.1
  persistentKeys:
    team: "my-team"

tracer:
  enabled: true
  captureHTTPS: true

metrics:
  namespace: "MyNamespace"
  captureColdStart: true
```

### 3. Usa nel tuo handler

```typescript
import { createFirstanceLogger, middy } from 'poc-logger';
import type { S3Event } from 'aws-lambda';

const obs = createFirstanceLogger({ configPath: './config.yaml' });

const handler = middy(async (event: S3Event) => {
  obs.logger.info('Processing event', { eventType: event.Records[0].eventName });
  return { statusCode: 200 };
}).use(obs.middleware());

export { handler };
```

---

## Quick Start — PHP

### 1. Installa come dipendenza

Aggiungi al tuo `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/FA-MCostantini/poc-logger.git"
    }
  ],
  "require": {
    "firstance/poc-logger": "^0.2.1"
  }
}
```

```bash
composer update
```

> Composer risolve le versioni direttamente dai git tag (es. `v0.2.1` → `0.2.0`). Consulta i [rilasci](https://github.com/FA-MCostantini/poc-logger/tags) per la versione più recente.

### 2. Crea `config.yaml`

Stesso file YAML mostrato nel Quick Start TypeScript — identico per entrambe le implementazioni.

### 3. Usa nel tuo handler

```php
use Firstance\LambdaObs\FirstanceLoggerFactory;

$obs = FirstanceLoggerFactory::create('./config.yaml');
$obs->logger->info('Processing event', ['eventType' => $event['type']]);
```

---

## Output OTel JSON

Entrambe le implementazioni producono la stessa struttura JSON su stdout, consumabile da CloudWatch Logs Insights:

```json
{
  "Timestamp": "2026-03-06T10:00:00.000Z",
  "SeverityText": "INFO",
  "SeverityNumber": 9,
  "Body": "Processing event",
  "Resource": {
    "service.name": "my-lambda",
    "service.version": "1.0.0",
    "telemetry.sdk.name": "poc-logger",
    "telemetry.sdk.version": "0.3.0",
    "service.language": "typescript",
    "faas.name": "my-lambda",
    "faas.version": "$LATEST",
    "faas.memory": "512",
    "faas.instance": "2026/03/10/[$LATEST]abc123",
    "cloud.provider": "aws",
    "cloud.region": "eu-south-1",
    "process.runtime.version": "22.0.0"
  },
  "Attributes": {
    "cold_start": true,
    "aws_request_id": "req-123",
    "eventType": "S3ObjectCreated"
  },
  "TraceId": "1-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx"
}
```

> I campi `service.name` e `service.version` vengono auto-scoperti da `package.json` (TS) o `composer.json` (PHP).
> I campi `telemetry.sdk.*`, `faas.*`, `process.runtime.version` sono popolati automaticamente dall'ambiente Lambda.

Query CloudWatch Logs Insights di esempio:

```
fields @timestamp, Body, SeverityText, Resource.service.name, Attributes.cold_start
| filter SeverityText = "ERROR"
| sort @timestamp desc
| limit 20
```

---

## Override tramite variabili d'ambiente

| Variabile d'ambiente        | Campo config YAML         | Default  |
|-----------------------------|---------------------------|----------|
| `POWERTOOLS_LOG_LEVEL`      | `logger.level`            | `INFO`   |
| `Firstance_OBS_SAMPLE_RATE`      | `logger.sampleRate`       | `0.1`    |
| `Firstance_OBS_METRICS_NAMESPACE`| `metrics.namespace`       | —        |

Le variabili d'ambiente hanno precedenza sui valori nel file `config.yaml` (12-factor app).

---

## Struttura del monorepo

```
poc-logger/
├── docker-compose.yml        # Servizi Docker test (network: host)
├── package.json              # Dipendenze npm, script build/test
├── composer.json             # Dipendenze Composer, autoload PSR-4
├── tsconfig.json             # Configurazione TypeScript base
├── tsconfig.build.json       # Build ESM
├── tsconfig.cjs.json         # Build CJS
├── vitest.config.ts          # Configurazione Vitest
├── phpunit.xml               # Configurazione PHPUnit
├── phpstan.neon              # Configurazione PHPStan
├── packages/
│   ├── typescript/           # Sorgenti TypeScript
│   │   ├── src/
│   │   └── tests/
│   └── php/                  # Sorgenti PHP
│       ├── src/
│       └── tests/
├── shared/
│   ├── schemas/
│   │   └── config-schema.json
│   └── config.example.yaml
├── tests/                    # Test cross-language
└── docs/                     # Documentazione tecnica
```

---

## Testing con Docker

Non sono richieste installazioni locali di Node o PHP. Tutti i test girano via Docker.
Il `docker-compose.yml` nella root gestisce build e rete (`network: host` per WSL2).

```bash
# Build di tutte le immagini
docker compose build

# Unit test TypeScript
docker compose run --rm ts-test

# Unit test PHP
docker compose run --rm php-test

# Test cross-language (output JSON identico)
bash tests/cross-language-test.sh
```

---

## Documentazione

| Documento | Descrizione |
|-----------|-------------|
| `docs/SPEC_REQUISITI.md` | Requisiti funzionali e non funzionali |
| `docs/SCHEMA_REFERENCE.md` | Riferimento completo dello schema di configurazione |
| `docs/QUERY_REFERENCE.md` | Query CloudWatch Logs Insights pronte all'uso |
| `docs/TEST_ENVIRONMENT.md` | Guida all'ambiente di test Docker |
| `docs/GLOSSARIO.md` | Terminologia del progetto |
| `docs/ACCEPTANCE_CRITERIA.md` | Criteri di accettazione PoC |
| `docs/DEPLOY.md` | Guida al deploy e utilizzo |

---

## Licenza

MIT-0 — Nessuna restrizione sull'uso, la modifica e la distribuzione.
