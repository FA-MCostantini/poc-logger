# Design: Dynamic Resource Metadata

## Obiettivo

Rendere i campi `Resource` del log OTel completamente dinamici, eliminando ogni configurazione manuale per i metadati di servizio. Aggiungere campi OTel standard per tracciabilità completa.

## Struttura Resource risultante

```json
{
  "Resource": {
    "service.name": "fa-bper-filedelivery",
    "service.version": "1.2.0",
    "telemetry.sdk.name": "poc-logger",
    "telemetry.sdk.version": "0.2.3",
    "service.language": "typescript",
    "faas.name": "fa-bper-filedelivery",
    "faas.version": "$LATEST",
    "faas.memory": "512",
    "faas.instance": "2025/03/09/[$LATEST]abc123",
    "cloud.provider": "aws",
    "cloud.region": "eu-south-1",
    "process.runtime.version": "22.14.0"
  }
}
```

## Fonti dati

| Campo | TypeScript | PHP |
|---|---|---|
| `service.name` | `package.json` name (walk up dirs) -> `"unknown"` | `InstalledVersions::getRootPackage()['name']` -> `"unknown"` |
| `service.version` | `package.json` version (walk up dirs) -> `"0.0.0"` | `InstalledVersions::getRootPackage()['pretty_version']` -> `"0.0.0"` |
| `telemetry.sdk.name` | costante `"poc-logger"` | costante `"poc-logger"` |
| `telemetry.sdk.version` | costante generata a build time da `package.json` version | `InstalledVersions::getVersion('firstance/poc-logger')` -> `"0.0.0"` |
| `process.runtime.version` | `process.version` (strip `v`) | `PHP_VERSION` |
| `faas.name` | gia presente (da Lambda context) | gia presente (da Lambda context) |
| `faas.version` | env `AWS_LAMBDA_FUNCTION_VERSION` -> `""` | env `AWS_LAMBDA_FUNCTION_VERSION` -> `""` |
| `faas.memory` | env `AWS_LAMBDA_FUNCTION_MEMORY_SIZE` -> `""` | env `AWS_LAMBDA_FUNCTION_MEMORY_SIZE` -> `""` |
| `faas.instance` | env `AWS_LAMBDA_LOG_STREAM_NAME` -> `""` | env `AWS_LAMBDA_LOG_STREAM_NAME` -> `""` |
| `cloud.provider` | costante `"aws"` | costante `"aws"` |
| `cloud.region` | env `AWS_REGION` | env `AWS_REGION` |

## Decisioni

1. `service.name` — sempre da package.json/Composer, fallback `"unknown"`. Niente YAML, niente env var override.
2. `service.version` — sempre da package.json/Composer, fallback `"0.0.0"`. Niente YAML.
3. `telemetry.sdk.version` (TS) — iniettata a build time via esbuild `define` per evitare I/O runtime.
4. `telemetry.sdk.version` (PHP) — `InstalledVersions::getVersion()` a runtime (zero I/O, da autoload).
5. Sezione `service:` rimossa dal config.yaml — YAGNI.
6. Env override `POWERTOOLS_SERVICE_NAME` per `service.name` rimosso.

## Modifiche architetturali

### 1. Config schema
- Rimuovere sezione `service` da Zod schema, YAML schema JSON, `ConfigDTO`, `config.example.yaml`

### 2. Config loader
- Rimuovere `ensureServiceName()`, env override `POWERTOOLS_SERVICE_NAME`
- TS: spostare `findProjectName()` e aggiungere `findProjectVersion()` come utility esportate
- PHP: spostare discovery in una classe `ServiceDiscovery` o metodi statici dedicati

### 3. OTel formatter
- TS: riceve `telemetry.sdk.version` come costante, `service.name/version` dalla factory, aggiunge nuovi campi da env
- PHP: stessa struttura, usa `InstalledVersions` + env vars

### 4. Factory
- TS/PHP: risolve `service.name` e `service.version` dal package manager, li passa al formatter

### 5. Build (TS)
- `build-cjs.mjs`: inietta `SDK_VERSION` come `define` da `package.json` version
- `tsconfig.build.json`: aggiungere file costanti se necessario

### 6. Documentazione
- `README.md`, `config.example.yaml`, `TEST_ENVIRONMENT.md`, `DEPLOY.md`, schema JSON condiviso

## Test

- Unit test del formatter: verificare tutti i nuovi campi Resource
- Cross-language test: verificare che TS e PHP producano la stessa struttura Resource (stesse chiavi)
- Smoke test CJS: continua a funzionare (cambia solo il contenuto del Resource)
