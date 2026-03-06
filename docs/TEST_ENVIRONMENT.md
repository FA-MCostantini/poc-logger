# Ambiente di Test — BPER Lambda Obs

**Versione**: 1.0.0 | **Data**: 2026-03-06

Tutti i test vengono eseguiti in container Docker. Non e' richiesta alcuna installazione
locale di Node.js, npm, PHP o Composer.

Vedere anche: [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)

---

## Prerequisiti

- Docker Engine >= 24.0
- Bash >= 5.0 (per lo script cross-language)
- Python 3 (per il parsing JSON nel cross-language test)

**Non** sono necessari:
- Node.js / npm
- PHP / Composer
- AWS CLI / credenziali AWS

---

## 1. Test TypeScript

### Build dell'immagine

```bash
docker build \
  -t bper-ts-test \
  -f packages/typescript/tests/Dockerfile \
  packages/typescript
```

L'immagine usa `node:20-alpine`. Il Dockerfile:
1. Copia `package.json` e installa le dipendenze con `npm install`
2. Copia `tsconfig.json`, `vitest.config.ts`, `src/` e `tests/`
3. Il CMD di default esegue `npx vitest run --reporter=verbose`

### Esecuzione test

```bash
docker run --rm bper-ts-test
```

### Esecuzione con coverage

```bash
docker run --rm bper-ts-test npx vitest run --coverage
```

Soglie di coverage configurate in `packages/typescript/vitest.config.ts`:
- **statements**: >= 90%
- **branches**: >= 85%

### Watch mode (sviluppo con volume mount)

```bash
docker run --rm -it \
  -v "$(pwd)/packages/typescript/src:/app/src" \
  -v "$(pwd)/packages/typescript/tests:/app/tests" \
  bper-ts-test npx vitest --reporter=verbose
```

---

## 2. Test PHP

### Build dell'immagine

```bash
docker build \
  -t bper-php-test \
  -f packages/php/tests/Dockerfile \
  packages/php
```

L'immagine usa `php:8.2-cli-alpine`. Il Dockerfile:
1. Installa `unzip`, `curl` e Composer
2. Copia `composer.json` e installa le dipendenze con `composer install --no-interaction`
3. Copia `phpunit.xml`, `phpstan.neon`, `src/` e `tests/`
4. Il CMD di default esegue `vendor/bin/phpunit --testdox`

### Esecuzione test

```bash
docker run --rm bper-php-test
```

### Verifica PHPStan (livello 8)

```bash
docker run --rm bper-php-test \
  vendor/bin/phpstan analyse src \
  --level=8 \
  --no-progress
```

Il file `phpstan.neon` configura:
- `level: 8` (massimo)
- `paths: [src]`
- `tmpDir: .phpstan.cache`

### Esecuzione con output XML (CI/CD)

```bash
docker run --rm bper-php-test \
  vendor/bin/phpunit --log-junit /tmp/junit.xml
```

---

## 3. Test Cross-Language (Parita' TS/PHP)

Lo script `tests/cross-language-test.sh` verifica che TS e PHP producano JSON con la stessa
struttura (chiavi identiche in ordine alfabetico).

### Prerequisiti

Le immagini `bper-ts-test` e `bper-php-test` devono essere gia' costruite (passi 1 e 2).

### Esecuzione

```bash
bash tests/cross-language-test.sh
```

### Cosa fa lo script

1. Esegue `emit-log-ts.ts` nel container TS con `npx tsx`, cattura l'output JSON
2. Esegue `emit-log-php.php` nel container PHP con `php`, cattura l'output JSON
3. Estrae e ordina alfabeticamente le chiavi di entrambi gli output con Python 3
4. Confronta i due set di chiavi
5. Stampa `PASS` e termina con exit code 0 se identici, `FAIL` e diff se diversi

### Output atteso (successo)

```
=== Cross-Language OTel Output Test ===

[1/4] Running TypeScript OTel log emission...
TS output: {"Timestamp":"...","SeverityText":"INFO",...}

[2/4] Running PHP OTel log emission...
PHP output: {"Timestamp":"...","SeverityText":"INFO",...}

[3/4] Comparing JSON structure...

[4/4] Result: PASS — TS and PHP produce identical OTel JSON structure
```

---

## 4. Esecuzione Completa (CI/CD pipeline)

Sequenza raccomandata per un pipeline CI:

```bash
# 1. Build
docker build -t bper-ts-test -f packages/typescript/tests/Dockerfile packages/typescript
docker build -t bper-php-test -f packages/php/tests/Dockerfile packages/php

# 2. Unit tests
docker run --rm bper-ts-test
docker run --rm bper-php-test

# 3. Static analysis (PHP)
docker run --rm bper-php-test vendor/bin/phpstan analyse src --level=8 --no-progress

# 4. Coverage (TS)
docker run --rm bper-ts-test npx vitest run --coverage

# 5. Cross-language parity
bash tests/cross-language-test.sh
```

Tutti i comandi devono restituire exit code 0 perche' il pipeline sia verde.

---

## 5. Struttura delle directory di test

```
packages/typescript/tests/
├── Dockerfile
├── fixtures/          # Dati di input per i test (YAML, JSON)
├── helpers/           # Utility condivise tra i test
├── unit/
│   ├── config/        # Test ConfigLoader e schema
│   ├── logger/        # Test OTelLogFormatter
│   ├── metrics/       # Test MetricsFactory
│   ├── middleware/    # Test MiddlewareChain
│   └── tracer/        # Test TracerFactory
├── integration/       # Test di integrazione (future)
└── factory.test.ts    # Test entry point createBperLogger

packages/php/tests/
├── Dockerfile
├── Fixtures/          # Dati di input (YAML)
├── Helpers/           # Utility condivise
├── Unit/
│   ├── Config/        # Test ConfigLoader e ConfigSchema
│   ├── Logger/        # Test OTelCloudWatchFormatter e Severity
│   ├── Metrics/       # Test EmfMetricsEmitter
│   └── Tracer/        # Test XRayTracerFactory
├── Integration/       # Test di integrazione (future)
└── BperLoggerFactoryTest.php

tests/                 # Test cross-language (root del monorepo)
├── cross-language-test.sh
├── emit-log-ts.ts
└── emit-log-php.php
```
