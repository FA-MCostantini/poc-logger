# PIANO DI ESECUZIONE MULTI-AGENTICO

## Progetto: `bper-lambda-obs` — Observability unificata per Lambda PHP + TypeScript

**Versione:** 1.0.0  
**Data:** 2026-03-05  
**Autore:** Mattia Costantini / Claude Opus  

---

## 1. Struttura del progetto

```
bper-lambda-obs/
│
├── README.md                          # Guida installazione e uso
├── PIANO_ESECUZIONE.md                # Questo file
├── CHANGELOG.md                       # Log delle modifiche
├── LICENSE                            # MIT-0
│
├── packages/
│   ├── typescript/                    # @bper/lambda-obs (npm)
│   │   ├── src/
│   │   │   ├── index.ts              # Export pubblici
│   │   │   ├── config/
│   │   │   │   ├── loader.ts         # ConfigLoader (yaml + env merge)
│   │   │   │   ├── schema.ts         # Zod schema validazione
│   │   │   │   └── types.ts          # Interfacce TypeScript
│   │   │   ├── logger/
│   │   │   │   ├── otel-formatter.ts # OTelLogFormatter (extends LogFormatter)
│   │   │   │   └── types.ts          # Interfacce OTel log record
│   │   │   ├── tracer/
│   │   │   │   └── tracer-factory.ts # Wrapper Powertools Tracer
│   │   │   ├── metrics/
│   │   │   │   └── metrics-factory.ts# Wrapper Powertools Metrics
│   │   │   ├── middleware/
│   │   │   │   └── middy-chain.ts    # Middy middleware chain orchestrator
│   │   │   └── factory.ts            # createBperLogger() — entry point
│   │   ├── tests/
│   │   │   ├── unit/
│   │   │   │   ├── config/
│   │   │   │   │   ├── loader.test.ts
│   │   │   │   │   └── schema.test.ts
│   │   │   │   ├── logger/
│   │   │   │   │   └── otel-formatter.test.ts
│   │   │   │   ├── tracer/
│   │   │   │   │   └── tracer-factory.test.ts
│   │   │   │   ├── metrics/
│   │   │   │   │   └── metrics-factory.test.ts
│   │   │   │   └── factory.test.ts
│   │   │   ├── integration/
│   │   │   │   ├── middleware-chain.integration.test.ts
│   │   │   │   ├── cloudwatch-output.integration.test.ts
│   │   │   │   └── config-env-merge.integration.test.ts
│   │   │   ├── fixtures/
│   │   │   │   ├── config.valid.yaml
│   │   │   │   ├── config.invalid.yaml
│   │   │   │   ├── config.minimal.yaml
│   │   │   │   └── .env.test
│   │   │   └── helpers/
│   │   │       ├── lambda-context.mock.ts
│   │   │       └── logger-spy.ts
│   │   ├── package.json
│   │   ├── tsconfig.json
│   │   ├── tsconfig.build.json
│   │   ├── vitest.config.ts
│   │   ├── .eslintrc.json
│   │   └── config.example.yaml
│   │
│   └── php/                           # bper/lambda-obs (composer)
│       ├── src/
│       │   ├── BperLoggerFactory.php  # Entry point — crea logger, tracer, metrics
│       │   ├── Config/
│       │   │   ├── ConfigLoader.php   # YAML + .env merge
│       │   │   ├── ConfigSchema.php   # Validazione struttura config
│       │   │   └── ConfigDTO.php      # DTO readonly per config validata
│       │   ├── Logger/
│       │   │   ├── OTelCloudWatchFormatter.php  # Monolog Formatter → JSON OTel
│       │   │   ├── LambdaContextProcessor.php   # Monolog Processor per Lambda context
│       │   │   └── ColdStartProcessor.php       # Monolog Processor per cold start
│       │   ├── Tracer/
│       │   │   └── XRayTracerFactory.php        # OpenTelemetry Tracer setup
│       │   ├── Metrics/
│       │   │   └── EmfMetricsEmitter.php        # CloudWatch EMF via stdout
│       │   └── Middleware/
│       │       └── LambdaObsMiddleware.php      # Kernel middleware per Symfony
│       ├── tests/
│       │   ├── Unit/
│       │   │   ├── Config/
│       │   │   │   ├── ConfigLoaderTest.php
│       │   │   │   ├── ConfigSchemaTest.php
│       │   │   │   └── ConfigDTOTest.php
│       │   │   ├── Logger/
│       │   │   │   ├── OTelCloudWatchFormatterTest.php
│       │   │   │   ├── LambdaContextProcessorTest.php
│       │   │   │   └── ColdStartProcessorTest.php
│       │   │   ├── Tracer/
│       │   │   │   └── XRayTracerFactoryTest.php
│       │   │   ├── Metrics/
│       │   │   │   └── EmfMetricsEmitterTest.php
│       │   │   └── BperLoggerFactoryTest.php
│       │   ├── Integration/
│       │   │   ├── MiddlewareChainTest.php
│       │   │   ├── CloudWatchOutputTest.php
│       │   │   └── ConfigEnvMergeTest.php
│       │   ├── Fixtures/
│       │   │   ├── config.valid.yaml
│       │   │   ├── config.invalid.yaml
│       │   │   ├── config.minimal.yaml
│       │   │   └── .env.test
│       │   └── Helpers/
│       │       ├── LambdaContextMock.php
│       │       └── LoggerOutputCapture.php
│       ├── composer.json
│       ├── phpunit.xml
│       ├── phpstan.neon
│       ├── .php-cs-fixer.dist.php
│       └── config.example.yaml
│
├── shared/
│   ├── config.example.yaml            # Template di riferimento
│   └── schemas/
│       └── config-schema.json         # JSON Schema condiviso per validazione
│
└── docs/
    ├── ACCEPTANCE_CRITERIA.md
    ├── DEPLOY.md
    ├── GLOSSARIO.md
    ├── QUERY_REFERENCE.md
    ├── SCHEMA_REFERENCE.md
    ├── SPEC_REQUISITI.md
    └── TEST_ENVIRONMENT.md
```

---

## 2. Sotto-agenti specializzati

### Panoramica agenti

```
┌─────────────────────────────────────────────────────────────────┐
│                    ORCHESTRATORE (Agent-0)                       │
│  Coordina sequenza, verifica dipendenze tra agenti,             │
│  gestisce config condivisa, valida output cross-language        │
└───────┬─────────┬─────────┬─────────┬─────────┬───────────┬────┘
        │         │         │         │         │           │
        ▼         ▼         ▼         ▼         ▼           ▼
   Agent-1    Agent-2   Agent-3   Agent-4   Agent-5     Agent-6
   CONFIG     TS-CORE   PHP-CORE  TESTING   DOCS        DEPLOY
```

---

### Agent-0: ORCHESTRATORE

**Ruolo:** Coordina tutti gli agenti, gestisce dipendenze e sequenza.

**Responsabilità:**
- Definire l'ordine di esecuzione (vedi §3 — Sequenza)
- Verificare che gli output di un agente siano input validi per il successivo
- Gestire il merge dei file condivisi (`config.example.yaml`, JSON Schema)
- Validare la coerenza cross-language (stessi campi, stesso formato output)
- Gestire CHANGELOG.md e versionamento

**Input:** Questo piano di esecuzione  
**Output:** Progetto completo, testato, documentato

---

### Agent-1: CONFIG — Configurazione condivisa

**Ruolo:** Progetta e implementa il layer di configurazione comune a PHP e TS.

**Skill attivate:** `php-senior-dev` (per standard PHP), `ears-doc` (per requisiti config)

**Responsabilità:**

| Task | File output | Dipende da |
|------|-------------|------------|
| Definire JSON Schema per `config.yaml` | `shared/schemas/config-schema.json` | — |
| Creare template YAML di esempio | `shared/config.example.yaml` | JSON Schema |
| Implementare `ConfigLoader` TS | `packages/typescript/src/config/loader.ts` | JSON Schema |
| Implementare Zod schema TS | `packages/typescript/src/config/schema.ts` | JSON Schema |
| Implementare `ConfigLoader` PHP | `packages/php/src/Config/ConfigLoader.php` | JSON Schema |
| Implementare `ConfigSchema` PHP | `packages/php/src/Config/ConfigSchema.php` | JSON Schema |
| Implementare `ConfigDTO` PHP | `packages/php/src/Config/ConfigDTO.php` | ConfigSchema |
| Implementare types TS | `packages/typescript/src/config/types.ts` | Zod schema |

**Regole:**
- `.env` sovrascrive SEMPRE `config.yaml` (12-factor compliance)
- Mapping env vars → config keys documentato esplicitamente
- Validazione a startup con errori chiari (no fail silenti)
- `ConfigDTO` PHP: `final readonly class` con promoted properties
- Types TS: `readonly` su tutte le proprietà, `as const` dove applicabile

**Criteri di accettazione:**
- Config valida → oggetto tipizzato (TS: tipo inferito da Zod, PHP: ConfigDTO)
- Config invalida → eccezione con messaggio leggibile (campo mancante, tipo errato)
- `.env` override → il valore env ha precedenza
- YAML assente → fallback a defaults ragionevoli + warning in log

---

### Agent-2: TS-CORE — Implementazione TypeScript

**Ruolo:** Implementa il pacchetto npm `@bper/lambda-obs`.

**Skill attivate:** `php-senior-dev` (sezione TypeScript strict mode)

**Responsabilità:**

| Task | File output | Dipende da |
|------|-------------|------------|
| `OTelLogFormatter` | `src/logger/otel-formatter.ts` | Agent-1 types |
| Logger types OTel | `src/logger/types.ts` | OTel spec |
| Tracer factory | `src/tracer/tracer-factory.ts` | Agent-1 config |
| Metrics factory | `src/metrics/metrics-factory.ts` | Agent-1 config |
| Middy middleware chain | `src/middleware/middy-chain.ts` | Logger + Tracer + Metrics |
| Factory `createBperLogger()` | `src/factory.ts` | Tutti i moduli |
| Export barrel | `src/index.ts` | Factory |
| `package.json` | `package.json` | — |
| `tsconfig.json` + build | `tsconfig*.json` | — |
| Vitest config | `vitest.config.ts` | — |
| ESLint config | `.eslintrc.json` | — |

**Regole (da `php-senior-dev` §TypeScript):**
- `strict: true` in tsconfig — non negoziabile
- `readonly` su tutte le proprietà delle interfacce
- Type guards per validazione runtime
- Exhaustive checking su union types con `never`
- Zero `any` — usare `unknown` + type guard
- Tree-shakeable: ogni modulo esportabile singolarmente

**Dettaglio `OTelLogFormatter`:**
Estende `LogFormatter` di `@aws-lambda-powertools/logger`.
Output conforme a OTel Logs Data Model:

```typescript
interface OTelLogRecord {
  readonly Timestamp: string;           // ISO 8601
  readonly SeverityText: string;        // DEBUG | INFO | WARN | ERROR
  readonly SeverityNumber: number;      // OTel severity number
  readonly Body: string;                // Messaggio log
  readonly Resource: {
    readonly 'service.name': string;
    readonly 'service.version': string;
    readonly 'service.language': 'typescript';
    readonly 'faas.name': string;
    readonly 'cloud.provider': 'aws';
    readonly 'cloud.region': string;
  };
  readonly Attributes: Record<string, unknown>;
  readonly TraceId?: string;
  readonly SpanId?: string;
}
```

**Dettaglio `createBperLogger()`:**
```typescript
interface BperObservability {
  readonly logger: Logger;
  readonly tracer: Tracer;
  readonly metrics: Metrics;
  middleware(): middy.MiddlewareObj;
}

function createBperLogger(options: BperLoggerOptions): BperObservability;
```

---

### Agent-3: PHP-CORE — Implementazione PHP

**Ruolo:** Implementa il pacchetto Composer `bper/lambda-obs`.

**Skill attivate:** `php-senior-dev` (regole complete)

**Responsabilità:**

| Task | File output | Dipende da |
|------|-------------|------------|
| `OTelCloudWatchFormatter` | `src/Logger/OTelCloudWatchFormatter.php` | Agent-1 config |
| `LambdaContextProcessor` | `src/Logger/LambdaContextProcessor.php` | — |
| `ColdStartProcessor` | `src/Logger/ColdStartProcessor.php` | — |
| `XRayTracerFactory` | `src/Tracer/XRayTracerFactory.php` | Agent-1 config |
| `EmfMetricsEmitter` | `src/Metrics/EmfMetricsEmitter.php` | Agent-1 config |
| `LambdaObsMiddleware` | `src/Middleware/LambdaObsMiddleware.php` | Logger + Tracer |
| `BperLoggerFactory` | `src/BperLoggerFactory.php` | Tutti i moduli |
| `composer.json` | `composer.json` | — |
| PHPUnit config | `phpunit.xml` | — |
| PHPStan config | `phpstan.neon` | — |
| CS Fixer config | `.php-cs-fixer.dist.php` | — |

**Regole (da `php-senior-dev`):**
- `declare(strict_types=1)` su OGNI file — nessuna eccezione
- `final readonly class` per DTO e value objects
- `enum` per type safety (no costanti stringa)
- `match()` invece di `switch`
- Named parameters SQL (`:param`)
- `LoggerInterface` PSR-3 iniettato via constructor
- PHPStan level 8 minimo
- Zero `mixed` senza giustificazione

**Dettaglio `OTelCloudWatchFormatter`:**
Estende `Monolog\Formatter\JsonFormatter`.
Produce lo STESSO output JSON dell'`OTelLogFormatter` TS:

```php
declare(strict_types=1);

final class OTelCloudWatchFormatter extends JsonFormatter
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $serviceVersion,
        private readonly string $region,
    ) {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        // Trasforma LogRecord Monolog → OTel JSON identico al TS
    }
}
```

**Dettaglio `BperLoggerFactory`:**
```php
declare(strict_types=1);

final class BperLoggerFactory
{
    public static function create(
        string $configPath = './config.yaml',
    ): BperObservability {
        // Carica config, crea Logger + Tracer + Metrics
    }
}

final readonly class BperObservability
{
    public function __construct(
        public Logger $logger,           // Monolog\Logger con OTel handler
        public XRayTracer $tracer,
        public EmfMetricsEmitter $metrics,
    ) {}
}
```

**Dettaglio `LambdaContextProcessor`:**
Monolog Processor che arricchisce ogni log record con:
- `faas.name` (nome funzione Lambda)
- `faas.version` (versione)
- `cloud.region`
- `aws_request_id`

**Dettaglio `ColdStartProcessor`:**
Monolog Processor che:
- Rileva cold start (prima invocazione del processo)
- Aggiunge `cold_start: true/false` a ogni log record
- Usa variabile statica per tracking (reset solo con nuovo container)

**Dettaglio `EmfMetricsEmitter`:**
Scrive metriche CloudWatch EMF direttamente su stdout:
```json
{
  "_aws": {
    "Timestamp": 1234567890,
    "CloudWatchMetrics": [{
      "Namespace": "BPERFileDelivery",
      "Dimensions": [["service", "environment"]],
      "Metrics": [{"Name": "ColdStart", "Unit": "Count"}]
    }]
  },
  "service": "bper-file-delivery",
  "environment": "production",
  "ColdStart": 1
}
```

---

### Agent-4: TESTING — Test unitari e di integrazione

**Ruolo:** Scrive e verifica tutti i test per entrambi i pacchetti.

**Skill attivate:** `php-senior-dev` (pattern test)

**Responsabilità:**

| Scope | Framework | Cartella |
|-------|-----------|----------|
| Unit TS | Vitest | `packages/typescript/tests/unit/` |
| Integration TS | Vitest | `packages/typescript/tests/integration/` |
| Unit PHP | PHPUnit 11 | `packages/php/tests/Unit/` |
| Integration PHP | PHPUnit 11 | `packages/php/tests/Integration/` |
| Fixtures condivise | YAML/ENV | `tests/fixtures/` in ciascun pacchetto |
| Helpers/Mocks | Custom | `tests/helpers/` in ciascun pacchetto |

**Matrice test — TypeScript:**

| Componente | Test unitario | Test integrazione |
|------------|---------------|-------------------|
| ConfigLoader | YAML parsing, env override, defaults, errori | Merge completo YAML + .env |
| Zod Schema | Validazione campi, tipi, required/optional | — |
| OTelLogFormatter | Formato output, severity mapping, resource fields | Output JSON su stdout |
| TracerFactory | Creazione tracer, config X-Ray | — |
| MetricsFactory | Creazione metrics, namespace, dimensions | — |
| Middy chain | Ordine middleware, inject context | Chain completa con Lambda mock |
| Factory | Creazione completa, opzioni inline vs YAML | End-to-end: config → log → output |

**Matrice test — PHP:**

| Componente | Test unitario | Test integrazione |
|------------|---------------|-------------------|
| ConfigLoader | YAML parsing, env override, defaults, eccezioni | Merge completo YAML + .env |
| ConfigSchema | Validazione campi, tipi, required/optional | — |
| ConfigDTO | Immutabilità, accesso proprietà | — |
| OTelCloudWatchFormatter | Formato output, severity mapping, resource fields | Output JSON su stdout |
| LambdaContextProcessor | Enrichment record, campi Lambda | — |
| ColdStartProcessor | Rilevamento cold start, flag toggle | — |
| XRayTracerFactory | Creazione tracer, config OTel | — |
| EmfMetricsEmitter | Formato EMF, dimensioni, metriche | Output stdout EMF |
| LambdaObsMiddleware | Inject context, lifecycle hooks | Chain completa |
| BperLoggerFactory | Creazione completa, opzioni inline vs YAML | End-to-end: config → log → output |

**Test cross-language (integrazione):**
- Entrambi i pacchetti producono lo STESSO output JSON per lo STESSO input config
- Stessa query CloudWatch Logs Insights funziona su output di entrambi
- Fixtures YAML identiche tra i due pacchetti

**Regole test:**
- Arrange / Act / Assert — sempre
- Un assert per test (eccezioni: test parametrizzati)
- Mock solo per dipendenze esterne (filesystem, rete)
- No test su metodi privati — testare via interfaccia pubblica
- Coverage target: 90% statements, 85% branches

---

### Agent-5: DOCS — Documentazione

**Ruolo:** Scrive tutta la documentazione tecnica e operativa.

**Skill attivate:** `ears-doc` (per SPEC_REQUISITI e ACCEPTANCE_CRITERIA)

**Responsabilità:**

| Documento | Contenuto | Dipende da |
|-----------|-----------|------------|
| `SPEC_REQUISITI.md` | Requisiti funzionali e non-funzionali in formato EARS | Agent-0 piano |
| `ACCEPTANCE_CRITERIA.md` | Criteri di accettazione per ogni componente | Agent-4 matrice test |
| `SCHEMA_REFERENCE.md` | Documentazione JSON Schema config, formato OTel output | Agent-1 schema |
| `GLOSSARIO.md` | Termini tecnici: OTel, EMF, X-Ray, cold start, ecc. | — |
| `QUERY_REFERENCE.md` | Query CloudWatch Logs Insights pronte all'uso | Agent-2 + Agent-3 output |
| `DEPLOY.md` | Come pubblicare su npm / Packagist, Lambda Layer, CI/CD | Agent-6 |
| `TEST_ENVIRONMENT.md` | Setup ambiente test locale, Docker, mocking AWS | Agent-4 |
| `README.md` (root) | Installazione, quick start, esempi per entrambi i linguaggi | Tutti gli agenti |

**Formato EARS per SPEC_REQUISITI (da skill `ears-doc`):**
- Ubiquitous: "The system shall..."
- Event-driven: "When [event], the system shall..."
- State-driven: "While [state], the system shall..."
- Unwanted behavior: "If [condition], then the system shall..."

**Regole documentazione:**
- Italiano per testo descrittivo
- Inglese per nomi tecnici, codice, API
- Ogni documento ha un header con versione e data
- Ogni documento ha una sezione "Changelog" in fondo
- Cross-reference tra documenti con link relativi

---

### Agent-6: DEPLOY — Build, packaging, CI/CD

**Ruolo:** Configura build, pubblicazione, e pipeline CI/CD.

**Responsabilità:**

| Task | Output |
|------|--------|
| Setup monorepo | Root `package.json` con workspace |
| Build TS | `tsconfig.build.json`, script build |
| Build PHP | `composer.json` con autoload PSR-4 |
| CI pipeline (GitHub Actions) | `.github/workflows/ci.yml` |
| Publish npm | Script + `.npmrc` per registry privato |
| Publish Packagist | Istruzioni per Satis/Private Packagist |
| Lambda Layer (opzionale) | Script per creare layer con dipendenze |

---

## 3. Sequenza di esecuzione

```
Fase 0 ─── SETUP ──────────────────────────────────────────────
│
│  Agent-0: Crea struttura cartelle, init monorepo
│  Agent-0: Crea package.json root, composer.json root
│
├── Milestone 0: Struttura progetto creata ✓
│
Fase 1 ─── FONDAMENTA ─────────────────────────────────────────
│
│  Agent-1: JSON Schema condiviso
│  Agent-1: config.example.yaml
│  Agent-1: ConfigLoader TS + Zod schema + types
│  Agent-1: ConfigLoader PHP + ConfigSchema + ConfigDTO
│
│  Agent-5: GLOSSARIO.md (può partire in parallelo)
│  Agent-5: SPEC_REQUISITI.md (può partire in parallelo)
│
├── Milestone 1: Config layer funzionante in entrambi i linguaggi ✓
│  Gate: test unitari ConfigLoader passano (TS + PHP)
│
Fase 2 ─── CORE TYPESCRIPT ────────────────────────────────────
│
│  Agent-2: OTelLogFormatter
│  Agent-2: TracerFactory
│  Agent-2: MetricsFactory
│  Agent-2: Middy middleware chain
│  Agent-2: Factory createBperLogger()
│  Agent-2: index.ts barrel export
│
│  Agent-4: Test unitari TS (in parallelo con Agent-2)
│
├── Milestone 2: Pacchetto TS compilabile e testato ✓
│  Gate: tutti i test unitari TS passano, build senza errori
│
Fase 3 ─── CORE PHP ───────────────────────────────────────────
│
│  Agent-3: OTelCloudWatchFormatter
│  Agent-3: LambdaContextProcessor + ColdStartProcessor
│  Agent-3: XRayTracerFactory
│  Agent-3: EmfMetricsEmitter
│  Agent-3: LambdaObsMiddleware
│  Agent-3: BperLoggerFactory
│
│  Agent-4: Test unitari PHP (in parallelo con Agent-3)
│
├── Milestone 3: Pacchetto PHP testato ✓
│  Gate: tutti i test unitari PHP passano, PHPStan level 8 OK
│
Fase 4 ─── INTEGRAZIONE ───────────────────────────────────────
│
│  Agent-4: Test integrazione TS
│  Agent-4: Test integrazione PHP
│  Agent-4: Test cross-language (stesso output JSON)
│
│  Agent-0: Validazione coerenza output TS ↔ PHP
│
├── Milestone 4: Output JSON identico tra TS e PHP ✓
│  Gate: stessa query CloudWatch funziona su entrambi
│
Fase 5 ─── DOCUMENTAZIONE ─────────────────────────────────────
│
│  Agent-5: ACCEPTANCE_CRITERIA.md
│  Agent-5: SCHEMA_REFERENCE.md
│  Agent-5: QUERY_REFERENCE.md
│  Agent-5: TEST_ENVIRONMENT.md
│
├── Milestone 5: Documentazione completa ✓
│
Fase 6 ─── DEPLOY & README ────────────────────────────────────
│
│  Agent-6: CI/CD pipeline
│  Agent-6: Script publish npm + Packagist
│  Agent-6: Lambda Layer script
│
│  Agent-5: DEPLOY.md
│  Agent-5: README.md (root)
│
├── Milestone 6: Progetto pubblicabile ✓
│  Gate: CI verde, README verificato, docs complete
│
Fase 7 ─── VALIDAZIONE FINALE ─────────────────────────────────
│
│  Agent-0: Review cross-agente
│  Agent-0: Verifica checklist qualità (vedi §4)
│  Agent-0: Tag v1.0.0
│
└── RELEASE ✓
```

### Diagramma dipendenze tra agenti

```
Agent-1 (CONFIG) ──────► Agent-2 (TS-CORE) ──────► Agent-4 (TEST TS)
        │                                                    │
        │                                                    ▼
        └──────────────► Agent-3 (PHP-CORE) ──────► Agent-4 (TEST PHP)
                                                             │
                                                             ▼
                                                    Agent-4 (CROSS-LANG)
                                                             │
Agent-5 (DOCS) ◄─── dipende da output di tutti ─────────────┘
                                                             │
Agent-6 (DEPLOY) ◄── dipende da build funzionante ──────────┘
```

---

## 4. Checklist qualità per milestone

### Per ogni file TypeScript:

- [ ] `strict: true` in tsconfig
- [ ] Zero `any` — solo `unknown` + type guard
- [ ] `readonly` su proprietà interfacce
- [ ] Exhaustive switch con `never`
- [ ] ESLint senza warning
- [ ] Test unitario associato

### Per ogni file PHP:

- [ ] `declare(strict_types=1)` in testa
- [ ] `final readonly class` per DTO/VO
- [ ] `enum` per valori discreti
- [ ] `match()` per branching
- [ ] `LoggerInterface` PSR-3 iniettato
- [ ] PHPStan level 8 senza errori
- [ ] Test unitario associato

### Per la documentazione:

- [ ] SPEC_REQUISITI usa formato EARS
- [ ] QUERY_REFERENCE ha almeno 10 query pronte
- [ ] README ha quick start < 5 minuti per ciascun linguaggio
- [ ] Tutti i link interni funzionano

### Cross-language:

- [ ] Stesso `config.yaml` funziona in entrambi
- [ ] Stesso formato JSON output OTel
- [ ] Stesse query CloudWatch Logs Insights
- [ ] Trace ID propagato PHP ↔ TS via `X-Amzn-Trace-Id`

---

## 5. Prompt per ciascun sotto-agente

Per attivare ciascun agente nel contesto di Claude Code o di una sessione Claude, usare i seguenti prompt iniziali.

### Agent-0 — Orchestratore
```
Sei l'orchestratore del progetto bper-lambda-obs.
Leggi PIANO_ESECUZIONE.md e coordina l'esecuzione sequenziale delle fasi.
Per ogni fase: attiva l'agente corretto, verifica il gate di milestone,
e solo dopo procedi alla fase successiva.
Mantieni CHANGELOG.md aggiornato dopo ogni milestone.
```

### Agent-1 — Config
```
Sei l'agente CONFIG per bper-lambda-obs.
Skill: php-senior-dev (sezioni TypeScript + PHP).
Compito: implementare il layer di configurazione condiviso.
Input: PIANO_ESECUZIONE.md §Agent-1.
Output: JSON Schema, config.example.yaml, ConfigLoader TS + PHP,
  schema di validazione, DTO, types.
Vincoli: .env sovrascrive YAML, validazione a startup con errori chiari,
  ConfigDTO PHP readonly, types TS readonly.
Inizia dal JSON Schema e poi genera gli altri file.
```

### Agent-2 — TypeScript Core
```
Sei l'agente TS-CORE per bper-lambda-obs.
Skill: php-senior-dev (sezione TypeScript strict mode).
Compito: implementare @bper/lambda-obs come pacchetto npm.
Input: PIANO_ESECUZIONE.md §Agent-2, output Agent-1 (types + config).
Output: OTelLogFormatter, TracerFactory, MetricsFactory,
  Middy middleware chain, Factory, barrel export.
Vincoli: strict TS, zero any, readonly, tree-shakeable.
Il LogFormatter DEVE estendere LogFormatter di @aws-lambda-powertools/logger.
L'output JSON DEVE essere identico a quello di Agent-3 (PHP).
```

### Agent-3 — PHP Core
```
Sei l'agente PHP-CORE per bper-lambda-obs.
Skill: php-senior-dev (regole complete).
Compito: implementare bper/lambda-obs come pacchetto Composer.
Input: PIANO_ESECUZIONE.md §Agent-3, output Agent-1 (ConfigDTO + config).
Output: OTelCloudWatchFormatter, Processors, TracerFactory,
  EmfMetricsEmitter, Middleware, BperLoggerFactory.
Vincoli: declare(strict_types=1), final readonly class, enum,
  PHPStan level 8, PSR-3 LoggerInterface.
L'output JSON DEVE essere identico a quello di Agent-2 (TS).
```

### Agent-4 — Testing
```
Sei l'agente TESTING per bper-lambda-obs.
Compito: scrivere test unitari e di integrazione per entrambi i pacchetti.
Input: PIANO_ESECUZIONE.md §Agent-4, codice di Agent-2 e Agent-3.
Output: test Vitest (TS), test PHPUnit (PHP), fixtures condivise.
Vincoli: AAA pattern, un assert per test, mock solo per I/O esterno,
  coverage 90% statements / 85% branches.
Test critico: stesso input YAML → stesso output JSON in TS e PHP.
```

### Agent-5 — Documentazione
```
Sei l'agente DOCS per bper-lambda-obs.
Skill: ears-doc (per requisiti e criteri di accettazione).
Compito: scrivere tutta la documentazione del progetto.
Input: PIANO_ESECUZIONE.md §Agent-5, output di tutti gli altri agenti.
Output: 8 file in docs/ + README.md root.
Vincoli: italiano per testo, inglese per codice/API,
  SPEC_REQUISITI in formato EARS, cross-reference tra documenti.
README deve permettere installazione in < 5 minuti.
```

### Agent-6 — Deploy
```
Sei l'agente DEPLOY per bper-lambda-obs.
Compito: configurare build, CI/CD, e pubblicazione pacchetti.
Input: PIANO_ESECUZIONE.md §Agent-6, package.json e composer.json finali.
Output: CI pipeline GitHub Actions, script publish,
  Lambda Layer script opzionale.
Vincoli: monorepo workspace, build TS con tsconfig.build.json,
  autoload PSR-4 per PHP.
```

---

## 6. Stima effort

| Fase | Agente | Effort stimato | Parallelizzabile |
|------|--------|---------------|------------------|
| 0 — Setup | Agent-0 | 0.5h | No |
| 1 — Config | Agent-1 | 3h | Parziale (DOCS) |
| 2 — TS Core | Agent-2 + Agent-4 | 4h | Sì (test in parallelo) |
| 3 — PHP Core | Agent-3 + Agent-4 | 5h | Sì (test in parallelo) |
| 4 — Integrazione | Agent-4 + Agent-0 | 2h | No |
| 5 — Documentazione | Agent-5 | 4h | Parziale |
| 6 — Deploy + README | Agent-5 + Agent-6 | 2h | Sì |
| 7 — Validazione | Agent-0 | 1h | No |
| **Totale** | | **~21h** | |

> Con parallelizzazione: ~14h effettive.
> Con Claude Code multi-agente: ~6-8h supervisionate.

---

## 7. Rischi e mitigazioni

| # | Rischio | Probabilità | Impatto | Mitigazione |
|---|---------|-------------|---------|-------------|
| 1 | Output JSON non identico PHP ↔ TS | Media | Alto | Test cross-language con snapshot |
| 2 | OTel PHP SDK troppo pesante per Lambda | Media | Medio | Profiling cold start, Lambda Layer |
| 3 | Breaking change Powertools v3 | Bassa | Alto | Pin versione, dependabot alerts |
| 4 | Config YAML non copre tutti i casi | Media | Basso | Schema estensibile, custom keys |
| 5 | EMF metrics non riconosciute da CW | Bassa | Medio | Test integrazione con CW reale |
| 6 | X-Ray trace non propaga PHP → TS | Bassa | Alto | Test con 2 Lambda in sequenza |

---

## Changelog

| Data | Versione | Descrizione |
|------|----------|-------------|
| 2026-03-05 | 1.0.0 | Piano iniziale |
