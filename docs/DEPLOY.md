# DEPLOY — Guida al Deploy e Utilizzo

> **Nota**: `firstance-lambda-obs` e' un Proof of Concept per uso interno Firstance.
> Non viene pubblicato su npm o Packagist. Questa guida descrive come
> integrarlo localmente nei progetti Lambda.

---

## 1. Utilizzo come dipendenza locale

### TypeScript — npm link / path install

**Opzione A: Installazione diretta dal percorso (consigliata)**

```bash
# Dalla root del progetto Lambda
npm install /percorso/assoluto/firstance-lambda-obs/packages/typescript
```

Il `package.json` del progetto risultante:

```json
{
  "dependencies": {
    "@firstance/lambda-obs": "file:/percorso/assoluto/firstance-lambda-obs/packages/typescript"
  }
}
```

**Opzione B: npm link (per sviluppo attivo)**

```bash
# Nel pacchetto firstance-lambda-obs
cd /percorso/assoluto/firstance-lambda-obs/packages/typescript
npm link

# Nel progetto Lambda
cd /percorso/del/progetto-lambda
npm link @firstance/lambda-obs
```

**Build richiesta prima dell'uso:**

```bash
cd /percorso/assoluto/firstance-lambda-obs/packages/typescript
npm install
npm run build
```

L'output compilato si trova in `packages/typescript/dist/`.

---

### PHP — Composer path repository

Aggiungi al `composer.json` del progetto Lambda:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/percorso/assoluto/firstance-lambda-obs/packages/php",
      "options": {
        "symlink": true
      }
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

Con `"symlink": true`, le modifiche al sorgente sono immediatamente visibili
senza dover rieseguire `composer install`.

---

## 2. Testing con Docker

Non sono richieste installazioni locali di Node.js o PHP.
Ogni pacchetto include un `Dockerfile` dedicato per i test.

### TypeScript — build e test

```bash
# Dalla root del monorepo
docker build \
  -t firstance-obs-ts \
  -f packages/typescript/tests/Dockerfile \
  packages/typescript

docker run --rm firstance-obs-ts
```

Output atteso: tutti i test Vitest superati con reporter verbose.

### PHP — build e test

```bash
docker build \
  -t firstance-obs-php \
  -f packages/php/tests/Dockerfile \
  packages/php

docker run --rm firstance-obs-php
```

Output atteso: tutti i test PHPUnit superati con formato testdox.

### Test cross-language (output JSON identico)

```bash
bash tests/cross-language-test.sh
```

Questo script:
1. Esegue entrambi i container
2. Cattura un log di esempio da ciascuno
3. Confronta la struttura JSON — il test passa solo se identica

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
cd packages/typescript
npm install && npm run build

# 2. Struttura layer
mkdir -p layer/nodejs/node_modules/@firstance
cp -r . layer/nodejs/node_modules/@firstance/lambda-obs

# 3. Zip e upload
cd layer
zip -r firstance-lambda-obs-layer.zip nodejs/
aws lambda publish-layer-version \
  --layer-name firstance-lambda-obs \
  --zip-file fileb://firstance-lambda-obs-layer.zip \
  --compatible-runtimes nodejs20.x
```

### PHP Layer

```bash
# 1. Install dipendenze
cd packages/php
composer install --no-dev --optimize-autoloader

# 2. Struttura layer
mkdir -p layer/php/vendor
cp -r vendor/* layer/php/vendor/
cp -r src/ layer/php/src/

# 3. Zip e upload
cd layer
zip -r firstance-lambda-obs-php-layer.zip php/
aws lambda publish-layer-version \
  --layer-name firstance-lambda-obs-php \
  --zip-file fileb://firstance-lambda-obs-php-layer.zip \
  --compatible-runtimes provided.al2
```

> **Nota PoC**: Questa procedura e' indicativa. In produzione richiederebbe
> un pipeline CI/CD dedicato e versionamento semantico dei layer.

---

## 5. Pipeline CI/CD (panoramica)

Se integrato in un pipeline, lo schema raccomandato e':

```
push → lint → test (Docker) → build → package → deploy (staging) → acceptance test → deploy (prod)
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
