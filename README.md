# firstance-lambda-obs

Libreria di observability unificata per AWS Lambda, con output OTel JSON compatibile con CloudWatch Logs Insights.
Disponibile in **TypeScript** (npm) e **PHP** (Composer). Entrambe le implementazioni producono strutture JSON identiche.

> Uso interno Firstance — Proof of Concept. Non pubblicato su npm o Packagist.

---

## Quick Start — TypeScript (< 5 minuti)

### 1. Installa come dipendenza locale

```bash
# Dalla root del tuo progetto Lambda
npm install /percorso/assoluto/firstance-lambda-obs/packages/typescript
```

### 2. Crea `config.yaml`

```yaml
service:
  name: "my-lambda"
  version: "1.0.0"

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
import { createFirstanceLogger } from '@firstance/lambda-obs';
import middy from '@middy/core';

const obs = createFirstanceLogger({ configPath: './config.yaml' });

const handler = middy(async (event, context) => {
  obs.logger.info('Processing event', { eventType: event.type });
  return { statusCode: 200 };
}).use(obs.middleware());

export { handler };
```

---

## Quick Start — PHP (< 5 minuti)

### 1. Installa come dipendenza locale

Aggiungi al tuo `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/percorso/assoluto/firstance-lambda-obs/packages/php"
    }
  ],
  "require": {
    "firstance/lambda-obs": "*"
  }
}
```

```bash
composer install
```

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
  "timestamp": "2026-03-06T10:00:00.000Z",
  "level": "INFO",
  "message": "Processing event",
  "service": "my-lambda",
  "version": "1.0.0",
  "cold_start": true,
  "xray_trace_id": "1-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx",
  "eventType": "S3ObjectCreated",
  "resource": {
    "service.name": "my-lambda",
    "service.version": "1.0.0",
    "cloud.provider": "aws",
    "faas.name": "my-lambda",
    "faas.version": "$LATEST"
  },
  "telemetry.sdk": {
    "language": "nodejs",
    "name": "aws-lambda-powertools"
  }
}
```

Query CloudWatch Logs Insights di esempio:

```
fields @timestamp, message, level, service, cold_start
| filter level = "ERROR"
| sort @timestamp desc
| limit 20
```

---

## Override tramite variabili d'ambiente

| Variabile d'ambiente        | Campo config YAML         | Default  |
|-----------------------------|---------------------------|----------|
| `POWERTOOLS_LOG_LEVEL`      | `logger.level`            | `INFO`   |
| `POWERTOOLS_SERVICE_NAME`   | `service.name`            | —        |
| `Firstance_OBS_SAMPLE_RATE`      | `logger.sampleRate`       | `0.1`    |
| `Firstance_OBS_METRICS_NAMESPACE`| `metrics.namespace`       | —        |

Le variabili d'ambiente hanno precedenza sui valori nel file `config.yaml` (12-factor app).

---

## Struttura del monorepo

```
firstance-lambda-obs/
├── packages/
│   ├── typescript/       # Pacchetto npm (@firstance/lambda-obs)
│   │   ├── src/
│   │   └── tests/
│   └── php/              # Pacchetto Composer (firstance/lambda-obs)
│       ├── src/
│       └── tests/
├── shared/
│   ├── schemas/
│   │   └── config-schema.json   # JSON Schema condiviso
│   └── config.example.yaml      # Template di configurazione
├── tests/                        # Test cross-language
└── docs/                         # Documentazione tecnica
```

---

## Testing con Docker

Non sono richieste installazioni locali di Node o PHP. Tutti i test girano via Docker.

### TypeScript

```bash
# Build e run test suite
docker build -t firstance-obs-ts packages/typescript \
  -f packages/typescript/tests/Dockerfile

docker run --rm firstance-obs-ts
```

### PHP

```bash
# Build e run test suite
docker build -t firstance-obs-php packages/php \
  -f packages/php/tests/Dockerfile

docker run --rm firstance-obs-php
```

### Test cross-language (output JSON identico)

```bash
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

MIT-0 — Uso interno Firstance. Nessuna restrizione sull'uso, la modifica e la distribuzione.
