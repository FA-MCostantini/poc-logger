# Changelog

Tutte le modifiche rilevanti al progetto `poc-logger` sono documentate in questo file.

Formato basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/).

---

## [1.0.0] - 2026-03-06

### Fase 0 — Setup monorepo

- Creata struttura directory del monorepo (`packages/typescript`, `packages/php`, `shared/`, `tests/`, `docs/`)
- Aggiunto `package.json` per il pacchetto TypeScript (`poc-logger` v1.0.0)
- Aggiunto `composer.json` per il pacchetto PHP (`firstance/poc-logger`)
- Configurato `tsconfig.json` e `tsconfig.build.json` con target ES2022
- Configurato `vitest.config.ts` per test unitari TypeScript
- Configurato `phpunit.xml` con test suite Unit e Integration
- Configurato `phpstan.neon` a livello 8 (massima severita')
- Aggiunto `Dockerfile` per test TypeScript (node:20-alpine + Vitest)
- Aggiunto `Dockerfile` per test PHP (php:8.2-cli-alpine + Composer + PHPUnit)
- Aggiunto `.gitignore` per entrambi i pacchetti

### Fase 1 — Configurazione condivisa

- Aggiunto JSON Schema condiviso (`shared/schemas/config-schema.json`) con validazione completa
- Aggiunto template di configurazione (`shared/config.example.yaml`) con commenti env override
- **TypeScript**:
  - `ConfigSchema` con Zod v3: validazione, defaults, tipi inferiti
  - `ConfigLoader`: parsing YAML + override variabili d'ambiente (12-factor)
  - `types.ts`: tipi `FirstanceConfig`, `LoggerConfig`, `TracerConfig`, `MetricsConfig`
  - Barrel export da `src/config/index.ts`
  - Test unitari Vitest per schema e loader
- **PHP**:
  - `ConfigDTO`: Data Transfer Object con proprieta' typed
  - `ConfigSchema`: validazione con valori default e messaggi d'errore
  - `ConfigLoader`: parsing YAML (`symfony/yaml`) + override env (`vlucas/phpdotenv`)
  - Test unitari PHPUnit per DTO, schema e loader
  - PHPStan level 8 verificato

### Fase 2 — Core TypeScript

- `OTelLogFormatter`: estensione `PowertoolsLogFormatter` per output JSON OTel-compatibile
- `TracerFactory`: factory per AWS X-Ray tracer con configurazione da `FirstanceConfig`
- `MetricsFactory`: factory per EMF metrics con namespace e cold start configurabili
- Middy middleware chain: logger + tracer + metrics integrati in singolo middleware
- `createFirstanceLogger()`: factory principale — entry point pubblico della libreria
- `src/index.ts`: barrel export di tutte le API pubbliche

### Fase 3 — Core PHP

- `OTelCloudWatchFormatter`: estensione `Monolog\Formatter\JsonFormatter` per output OTel-compatibile
- `LambdaContextProcessor`: Monolog processor per arricchire i log con contesto Lambda (function name, version, ARN, request ID)
- `ColdStartProcessor`: Monolog processor per rilevamento e annotazione cold start
- `XRayTracerFactory`: factory per integrazione AWS X-Ray con Monolog
- `EmfMetricsEmitter`: emettitore metriche in formato CloudWatch EMF
- `FirstanceLoggerFactory`: factory principale PHP — entry point pubblico della libreria

### Fase 4 — Test di integrazione cross-language

- `tests/cross-language-test.sh`: script Bash che avvia entrambi i container Docker, cattura un log di esempio da ciascuno e verifica che la struttura JSON sia identica tra TypeScript e PHP
- Verificata equivalenza strutturale dell'output OTel per: livello, messaggio, service, resource, telemetry.sdk, cold_start, xray_trace_id

### Fase 5 — Documentazione

- `docs/SPEC_REQUISITI.md`: requisiti funzionali (RF) e non funzionali (RNF) con priorita' MoSCoW
- `docs/SCHEMA_REFERENCE.md`: riferimento completo di tutti i campi `config.yaml` con tipi, default e override env
- `docs/QUERY_REFERENCE.md`: 15+ query CloudWatch Logs Insights pronte all'uso (errori, performance, cold start, metriche)
- `docs/TEST_ENVIRONMENT.md`: guida completa all'ambiente di test Docker, prerequisiti, comandi, troubleshooting
- `docs/GLOSSARIO.md`: terminologia del progetto (OTel, EMF, X-Ray, Powertools, Middy, PHPStan, ecc.)
- `docs/ACCEPTANCE_CRITERIA.md`: criteri di accettazione del PoC con soglie quantitative e checklist di verifica

### Fase 6 — Deploy e README

- `README.md`: guida introduttiva con Quick Start TypeScript e PHP (< 5 minuti), output OTel di esempio, tabella override env, comandi Docker, link documentazione
- `docs/DEPLOY.md`: guida dettagliata a npm link / Composer path repository, testing Docker, bozza Lambda Layer, panoramica pipeline CI/CD
- `CHANGELOG.md`: questo file

---

## Versioni dipendenze

| Dipendenza | Versione |
|-----------|---------|
| Node.js | >= 20.0.0 |
| PHP | ^8.2 |
| TypeScript | ^5.4 |
| Zod | ^3.23 |
| js-yaml | ^4.1 |
| dotenv | ^16.4 |
| @aws-lambda-powertools/logger | ^2.31 |
| @aws-lambda-powertools/tracer | ^2.31 |
| @aws-lambda-powertools/metrics | ^2.31 |
| @middy/core | ^5.0 |
| Vitest | ^3.0 |
| Monolog | ^3.0 |
| open-telemetry/sdk | ^1.0 |
| symfony/yaml | ^6.0 o ^7.0 |
| vlucas/phpdotenv | ^5.5 |
| PHPUnit | ^11.0 |
| PHPStan | ^2.0 (level 8) |
