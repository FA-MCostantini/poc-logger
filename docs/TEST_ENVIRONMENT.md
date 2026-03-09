# Ambiente di Test — poc-logger

**Versione**: 0.2.1 | **Data**: 2026-03-09

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
  -t firstance-ts-test \
  -f packages/typescript/tests/Dockerfile \
  .
```

L'immagine usa `node:20-alpine`. Il Dockerfile:
1. Copia `package.json` e installa le dipendenze con `npm install`
2. Copia `tsconfig.json`, `tsconfig.build.json`, `tsconfig.cjs.json`, `vitest.config.ts`, `packages/typescript/src/` e `packages/typescript/tests/`
3. Il CMD di default esegue `npx vitest run --reporter=verbose`

### Esecuzione test

```bash
docker run --rm firstance-ts-test
```

### Esecuzione con coverage

```bash
docker run --rm firstance-ts-test npx vitest run --coverage
```

Soglie di coverage configurate in `vitest.config.ts`:
- **statements**: >= 90%
- **branches**: >= 85%

### Watch mode (sviluppo con volume mount)

```bash
docker run --rm -it \
  -v "$(pwd)/packages/typescript/src:/app/packages/typescript/src" \
  -v "$(pwd)/packages/typescript/tests:/app/packages/typescript/tests" \
  firstance-ts-test npx vitest --reporter=verbose
```

---

## 2. Test PHP

### Build dell'immagine

```bash
docker build \
  -t firstance-php-test \
  -f packages/php/tests/Dockerfile \
  .
```

L'immagine usa `php:8.2-cli-alpine`. Il Dockerfile:
1. Installa `unzip`, `curl` e Composer
2. Copia `composer.json` e installa le dipendenze con `composer install --no-interaction`
3. Copia `phpunit.xml`, `phpstan.neon`, `packages/php/src/` e `packages/php/tests/`
4. Il CMD di default esegue `vendor/bin/phpunit --testdox`

### Esecuzione test

```bash
docker run --rm firstance-php-test
```

### Verifica PHPStan (livello 8)

```bash
docker run --rm firstance-php-test \
  vendor/bin/phpstan analyse packages/php/src \
  --level=8 \
  --no-progress
```

Il file `phpstan.neon` configura:
- `level: 8` (massimo)
- `paths: [packages/php/src]`
- `tmpDir: .phpstan.cache`

### Esecuzione con output XML (CI/CD)

```bash
docker run --rm firstance-php-test \
  vendor/bin/phpunit --log-junit /tmp/junit.xml
```

---

## 3. Test Cross-Language (Parita' TS/PHP)

Lo script `tests/cross-language-test.sh` verifica che TS e PHP producano JSON con la stessa
struttura (chiavi identiche in ordine alfabetico).

### Prerequisiti

Le immagini `firstance-ts-test` e `firstance-php-test` devono essere gia' costruite (passi 1 e 2).

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
docker build -t firstance-ts-test -f packages/typescript/tests/Dockerfile .
docker build -t firstance-php-test -f packages/php/tests/Dockerfile .

# 2. Unit tests
docker run --rm firstance-ts-test
docker run --rm firstance-php-test

# 3. Static analysis (PHP)
docker run --rm firstance-php-test vendor/bin/phpstan analyse packages/php/src --level=8 --no-progress

# 4. Coverage (TS)
docker run --rm firstance-ts-test npx vitest run --coverage

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
└── unit/
    ├── config/        # Test ConfigLoader e schema
    ├── logger/        # Test OTelLogFormatter
    ├── metrics/       # Test MetricsFactory
    ├── middleware/    # Test MiddlewareChain
    ├── tracer/        # Test TracerFactory
    └── factory.test.ts    # Test entry point createFirstanceLogger

packages/php/tests/
├── Dockerfile
├── Fixtures/          # Dati di input (YAML)
├── Helpers/           # Utility condivise
├── Unit/
│   ├── Config/        # Test ConfigLoader e ConfigSchema
│   ├── Logger/        # Test OTelCloudWatchFormatter e Severity
│   ├── Metrics/       # Test EmfMetricsEmitter
│   ├── Tracer/        # Test XRayTracerFactory
│   └── FirstanceLoggerFactoryTest.php
└── Integration/       # Test di integrazione (future)

tests/                 # Test cross-language (root del monorepo)
├── cross-language-test.sh
├── emit-log-ts.ts
└── emit-log-php.php
```

---

## 6. File di configurazione (root)

Tutti i file di configurazione risiedono nella root del monorepo:

| File | Scopo |
|------|-------|
| `package.json` | Dipendenze npm, script build/test |
| `composer.json` | Dipendenze Composer, autoload PSR-4 |
| `tsconfig.json` | Configurazione TypeScript base |
| `tsconfig.build.json` | Build ESM (`packages/typescript/dist/esm/`) |
| `tsconfig.cjs.json` | Build CJS (`packages/typescript/dist/cjs/`) |
| `vitest.config.ts` | Configurazione Vitest |
| `phpunit.xml` | Configurazione PHPUnit |
| `phpstan.neon` | Configurazione PHPStan |
