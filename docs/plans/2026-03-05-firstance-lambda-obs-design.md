# Design — firstance-lambda-obs

**Data:** 2026-03-05
**Versione:** 1.0.0

## Obiettivo

Monorepo con due pacchetti gemelli (TypeScript npm + PHP Composer) che producono log/trace/metrics in formato OpenTelemetry identico per AWS Lambda, interrogabili con le stesse query CloudWatch Logs Insights.

## Decisioni di design

### Branching
- Branch di lavoro: `feature/firstance-lambda-obs` da `main`

### Testing
- Docker container per pacchetto, nessuna dipendenza locale
- `packages/typescript/tests/Dockerfile` — Node 20 + Vitest
- `packages/php/tests/Dockerfile` — PHP 8.2 + PHPUnit 11
- `tests/cross-language-test.sh` — script bash che builda entrambi i container e confronta output JSON
- Nessun docker-compose

### Pubblicazione
- Uso interno, nessuna pubblicazione reale su npm/Packagist (PoC)

### Convenzioni
- Italiano per testo descrittivo
- Inglese per codice, API, commenti nel codice

## Struttura

```
firstance-lambda-obs/
├── packages/
│   ├── typescript/
│   │   ├── src/
│   │   ├── tests/
│   │   │   ├── Dockerfile
│   │   │   ├── unit/
│   │   │   ├── integration/
│   │   │   ├── fixtures/
│   │   │   └── helpers/
│   │   ├── package.json
│   │   ├── tsconfig.json
│   │   └── vitest.config.ts
│   └── php/
│       ├── src/
│       ├── tests/
│       │   ├── Dockerfile
│       │   ├── Unit/
│       │   ├── Integration/
│       │   ├── Fixtures/
│       │   └── Helpers/
│       ├── composer.json
│       └── phpunit.xml
├── shared/
│   ├── config.example.yaml
│   └── schemas/
│       └── config-schema.json
├── tests/
│   └── cross-language-test.sh
├── docs/
├── README.md
├── CHANGELOG.md
└── LICENSE
```

## Fasi di esecuzione

Scope attuale: Fase 0 (Setup) + Fase 1 (Config layer).
Fasi successive da pianificare dopo validazione Fase 1.

## Changelog

| Data | Descrizione |
|------|-------------|
| 2026-03-05 | Design iniziale approvato |
