# DEPLOY — Guida al Deploy e Utilizzo

> **Nota**: `poc-logger` e' un Proof of Concept per uso interno Firstance.
> Non viene pubblicato su npm o Packagist. Questa guida descrive come
> integrarlo nei progetti Lambda.

---

## 1. Utilizzo come dipendenza

### TypeScript — installazione da GitHub

```bash
npm install github:FA-MCostantini/poc-logger#v0.2.1
```

Il `package.json` del progetto risultante:

```json
{
  "dependencies": {
    "poc-logger": "github:FA-MCostantini/poc-logger#v0.2.1"
  }
}
```

La compilazione TypeScript avviene automaticamente durante l'installazione tramite lo script `prepare`.

**Build manuale (sviluppo locale):**

```bash
npm install
npm run build
```

L'output compilato si trova in `packages/typescript/dist/` (ESM + CJS).

---

### PHP — Composer con versionamento semver

Aggiungi al `composer.json` del progetto Lambda:

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

Composer risolve le versioni direttamente dai git tag (es. `v0.2.1` -> `0.2.1`).

---

## 2. Testing con Docker

Non sono richieste installazioni locali di Node.js o PHP.
Il `docker-compose.yml` nella root del monorepo definisce i servizi di test
con `network: host` per evitare problemi di rete bridge su WSL2.

### Build e test (con Docker Compose)

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

### Build manuale (senza Docker Compose)

```bash
docker build --network=host -t firstance-ts-test -f packages/typescript/tests/Dockerfile .
docker build --network=host -t firstance-php-test -f packages/php/tests/Dockerfile .

docker run --rm firstance-ts-test
docker run --rm firstance-php-test
```

---

## 3. Variabili d'ambiente per Lambda

Configura nel template SAM / CloudFormation o nelle variabili d'ambiente Lambda:

```yaml
# template.yaml (SAM)
Globals:
  Function:
    Environment:
      Variables:
        POWERTOOLS_LOG_LEVEL: INFO
        POWERTOOLS_SERVICE_NAME: !Ref ServiceName
        Firstance_OBS_METRICS_NAMESPACE: FirstanceFileDelivery
        Firstance_OBS_SAMPLE_RATE: "0.1"
```

---

## 4. Lambda Layer (bozza opzionale)

Per distribuire la libreria come Lambda Layer condiviso tra piu' funzioni:

### TypeScript Layer

```bash
# 1. Build del pacchetto
npm install && npm run build

# 2. Struttura layer
mkdir -p layer/nodejs/node_modules/@firstance
cp -r . layer/nodejs/node_modules/@firstance/poc-logger

# 3. Zip e upload
cd layer
zip -r poc-logger-layer.zip nodejs/
aws lambda publish-layer-version \
  --layer-name poc-logger \
  --zip-file fileb://poc-logger-layer.zip \
  --compatible-runtimes nodejs20.x
```

### PHP Layer

```bash
# 1. Install dipendenze
composer install --no-dev --optimize-autoloader

# 2. Struttura layer
mkdir -p layer/php/vendor
cp -r vendor/* layer/php/vendor/
cp -r packages/php/src/ layer/php/src/

# 3. Zip e upload
cd layer
zip -r poc-logger-php-layer.zip php/
aws lambda publish-layer-version \
  --layer-name poc-logger-php \
  --zip-file fileb://poc-logger-php-layer.zip \
  --compatible-runtimes provided.al2
```

> **Nota PoC**: Questa procedura e' indicativa. In produzione richiederebbe
> un pipeline CI/CD dedicato e versionamento semantico dei layer.

---

## 5. Pipeline CI/CD (panoramica)

Se integrato in un pipeline, lo schema raccomandato e':

```
push -> lint -> test (Docker) -> build -> package -> deploy (staging) -> acceptance test -> deploy (prod)
```

Passi chiave:

| Fase | TypeScript | PHP |
|------|-----------|-----|
| Lint | `npm run lint` (ESLint) | `vendor/bin/phpstan analyse` (level 8) |
| Test | `docker run firstance-obs-ts` | `docker run firstance-obs-php` |
| Build | `npm run build` | `composer install --no-dev` |
| Cross-lang | `bash tests/cross-language-test.sh` | (stesso script) |

---

## 6. Checklist pre-deploy

- [ ] `npm run build` completato senza errori (TypeScript)
- [ ] Tutti i test Vitest superati
- [ ] Tutti i test PHPUnit superati
- [ ] PHPStan level 8 senza errori
- [ ] Test cross-language superato (output JSON identico)
- [ ] Variabili d'ambiente configurate nella funzione Lambda
- [ ] File `config.yaml` incluso nel pacchetto di deploy

---

Per dettagli sull'ambiente di test, vedi [`TEST_ENVIRONMENT.md`](TEST_ENVIRONMENT.md).
Per i criteri di accettazione del PoC, vedi [`ACCEPTANCE_CRITERIA.md`](ACCEPTANCE_CRITERIA.md).
