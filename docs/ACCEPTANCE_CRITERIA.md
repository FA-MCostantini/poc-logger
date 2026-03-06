# Criteri di Accettazione — Firstance Lambda Obs

**Versione**: 1.0.0 | **Data**: 2026-03-06

Criteri verificabili per ogni componente del sistema. Ogni criterio e' collegato ai requisiti
in [SPEC_REQUISITI.md](SPEC_REQUISITI.md) e ai test in [TEST_ENVIRONMENT.md](TEST_ENVIRONMENT.md).

---

## 1. ConfigLoader (TS: `loadConfig` | PHP: `ConfigLoader::load`)

| # | Criterio | Requisito |
|---|---|---|
| AC-CFG-01 | Dato un file YAML valido con tutti i campi, restituisce un oggetto config tipizzato con i valori corretti. | REQ-CFG-01 |
| AC-CFG-02 | Dato un file YAML senza `service.name`, il valore di default viene estratto dal campo `name` di `package.json` (TS) o `composer.json` (PHP) del progetto consumatore. Tutti gli altri default sono applicati (livello INFO, sampleRate 1.0, tracer abilitato, namespace Default, captureColdStart true). | REQ-CFG-05 |
| AC-CFG-03 | Con `POWERTOOLS_LOG_LEVEL=DEBUG` impostato, il campo `logger.level` nel config e' `"DEBUG"` indipendentemente dal valore YAML. | REQ-CFG-02 |
| AC-CFG-04 | Con `POWERTOOLS_SERVICE_NAME=override-name`, il campo `service.name` e' `"override-name"`. | REQ-CFG-02 |
| AC-CFG-05 | Con `Firstance_OBS_SAMPLE_RATE=0.05` (stringa), il campo `logger.sampleRate` e' il numero `0.05`. | REQ-CFG-02 |
| AC-CFG-06 | Se il file YAML non esiste, il ConfigLoader tenta di crearlo con valori di default (incluso `service.name` da `package.json`/`composer.json`). Se la creazione fallisce, emette un warning non bloccante e prosegue con i default in memoria. | REQ-CFG-03 |
| AC-CFG-07 | Se il YAML manca del campo `service.name`, il ConfigLoader usa come default il valore di `name` da `package.json` (TS) o `composer.json` (PHP) del progetto consumatore, senza lanciare errore. | REQ-CFG-04 |
| AC-CFG-08 | Se `logger.level` contiene un valore non valido (es. `"VERBOSE"`), lancia un errore di validazione. | REQ-CFG-04 |
| AC-CFG-09 | TS e PHP producono oggetti config equivalenti dati gli stessi input YAML e le stesse variabili d'ambiente. | REQ-CFG-06 |

---

## 2. OTelLogFormatter (TS) / OTelCloudWatchFormatter (PHP)

| # | Criterio | Requisito |
|---|---|---|
| AC-FMT-01 | L'output JSON contiene esattamente le chiavi top-level: `Timestamp`, `SeverityText`, `SeverityNumber`, `Body`, `Resource`, `Attributes`. | REQ-LOG-01 |
| AC-FMT-02 | Il campo `Timestamp` e' in formato ISO 8601 UTC con precisione al millisecondo (es. `"2026-03-06T10:30:00.123Z"`). | REQ-LOG-05 |
| AC-FMT-03 | Il campo `SeverityNumber` e' `5` per DEBUG, `9` per INFO, `13` per WARN, `17` per ERROR. | REQ-SEV-01 |
| AC-FMT-04 | Il campo `Resource` contiene: `service.name`, `service.version`, `service.language`, `faas.name`, `cloud.provider = "aws"`, `cloud.region`. | REQ-LOG-03 |
| AC-FMT-05 | Con X-Ray trace ID disponibile, il campo `TraceId` e' presente con il valore corretto. | REQ-TRACE-01, REQ-LOG-02 |
| AC-FMT-06 | Senza X-Ray trace ID, il campo `TraceId` e' assente dal JSON (non null, non stringa vuota). | REQ-TRACE-03 |
| AC-FMT-07 | Il campo `Attributes` contiene `cold_start` e `aws_request_id` quando il Lambda context e' iniettato. | REQ-LOG-04 |
| AC-FMT-08 | Gli attributi custom passati al log (es. `{ orderId: "ORD-001" }`) appaiono in `Attributes`. | REQ-LOG-04 |
| AC-FMT-09 | I `persistentKeys` configurati (es. `team`, `partner`) appaiono in `Attributes` di ogni record. | REQ-LOG-06 |
| AC-FMT-10 | PHP: Monolog level 400 (ERROR) → `SeverityNumber = 17`. Monolog level 300 (WARNING) → `SeverityNumber = 13`. Monolog level 500+ → `SeverityNumber = 17`. | REQ-SEV-02 |
| AC-FMT-11 | L'output e' una singola riga JSON terminata da `\n` (nessun pretty-print). | REQ-LOG-01 |

---

## 3. TracerFactory (TS: `createTracer`) / XRayTracerFactory (PHP)

| # | Criterio | Requisito |
|---|---|---|
| AC-TRC-01 | TS: `createTracer(config)` restituisce un'istanza `Tracer` configurata con il `serviceName` dal config. | REQ-FAC-01 |
| AC-TRC-02 | TS: Con `tracer.enabled = false` nel config, il Tracer e' inizializzato in modalita' disabilitata. | — |
| AC-TRC-03 | PHP: `XRayTracerFactory::isEnabled()` restituisce il valore di `config.tracerEnabled`. | — |
| AC-TRC-04 | PHP: `XRayTracerFactory::getTraceId()` restituisce il valore di `_X_AMZN_TRACE_ID` se presente, `null` altrimenti. | REQ-TRACE-02 |
| AC-TRC-05 | PHP: Con `_X_AMZN_TRACE_ID` non impostato, `getTraceId()` restituisce `null`. | REQ-TRACE-03 |

---

## 4. MetricsFactory (TS: `createMetrics`) / EmfMetricsEmitter (PHP)

| # | Criterio | Requisito |
|---|---|---|
| AC-MET-01 | TS: `createMetrics(config)` restituisce un'istanza `Metrics` con namespace e serviceName dal config. | REQ-MET-01, REQ-FAC-01 |
| AC-MET-02 | PHP: `EmfMetricsEmitter::putMetric("MyMetric", 42.0, "Count", ["env" => "prod"])` scrive un record EMF valido su STDOUT. | REQ-MET-01 |
| AC-MET-03 | Il record EMF contiene il namespace dal config in `_aws.CloudWatchMetrics[0].Namespace`. | REQ-MET-03 |
| AC-MET-04 | Il record EMF contiene la dimensione `service` con il nome del servizio. | REQ-MET-02 |
| AC-MET-05 | Il valore della metrica appare al top-level del JSON con la chiave uguale al nome della metrica. | REQ-MET-01 |
| AC-MET-06 | PHP: `emitColdStartMetric(true)` scrive un record EMF con `ColdStart = 1.0`. | REQ-CS-02 |
| AC-MET-07 | PHP: `emitColdStartMetric(false)` scrive un record EMF con `ColdStart = 0.0`. | REQ-CS-02 |
| AC-MET-08 | PHP: Con `metricsCaptureColdStart = false`, `emitColdStartMetric()` non scrive nulla su STDOUT. | REQ-CS-03 |

---

## 5. Middleware Chain (TS: `createMiddlewareChain`)

| # | Criterio | Requisito |
|---|---|---|
| AC-MW-01 | `createMiddlewareChain(options)` restituisce un oggetto con le proprieta' `before`, `after`, `onError`. | REQ-MW-01 |
| AC-MW-02 | La fase `before` esegue Tracer, poi Logger, poi Metrics nell'ordine. | REQ-MW-02 |
| AC-MW-03 | La fase `after` esegue Metrics, poi Logger, poi Tracer nell'ordine (inverso). | REQ-MW-02 |
| AC-MW-04 | La fase `onError` esegue solo il middleware Tracer. | REQ-MW-02 |
| AC-MW-05 | Con `logEvent = true`, il logger middleware registra il payload dell'evento Lambda. | REQ-MW-03 |
| AC-MW-06 | Con `captureColdStart = true`, il metrics middleware emette la metrica `ColdStart` alla prima invocazione. | REQ-CS-01 |

---

## 6. Factory Entry Point (TS: `createFirstanceLogger` | PHP: `FirstanceLoggerFactory::create`)

| # | Criterio | Requisito |
|---|---|---|
| AC-FAC-01 | TS: `createFirstanceLogger({ configPath })` restituisce un oggetto con `logger`, `tracer`, `metrics` e `middleware`. | REQ-FAC-01 |
| AC-FAC-02 | TS: `obs.middleware()` restituisce un `MiddlewareLikeObj` valido compatibile con Middy. | REQ-MW-01 |
| AC-FAC-03 | PHP: `FirstanceLoggerFactory::create(configPath)` restituisce un `FirstanceObservability` con `logger`, `tracer`, `metrics`. | REQ-FAC-02 |
| AC-FAC-04 | Tutti i componenti restituiti usano la stessa configurazione (stessi `serviceName`, `namespace`, ecc.). | REQ-FAC-03 |
| AC-FAC-05 | Il formatter del logger e' un'istanza di `OTelLogFormatter` (TS) / `OTelCloudWatchFormatter` (PHP). | REQ-LOG-01 |

---

## 7. Cross-Language Parity

| # | Criterio | Requisito |
|---|---|---|
| AC-XL-01 | L'output OTel di TS e PHP, dato lo stesso input, contiene gli stessi top-level keys: `Timestamp`, `SeverityText`, `SeverityNumber`, `Body`, `Resource`, `Attributes`. | REQ-XL-01 |
| AC-XL-02 | L'output OTel di TS e PHP contiene gli stessi keys nel sotto-oggetto `Resource`. | REQ-XL-01 |
| AC-XL-03 | I valori di `SeverityText` e `SeverityNumber` sono identici in TS e PHP per ogni livello (DEBUG, INFO, WARN, ERROR). | REQ-SEV-03, REQ-XL-01 |
| AC-XL-04 | Lo script `tests/cross-language-test.sh` termina con exit code 0. | REQ-XL-02 |
| AC-XL-05 | Con trace ID presente, entrambe le implementazioni includono `TraceId` nella stessa posizione. | REQ-XL-01 |

---

## Definizione di "Done" per il progetto

Il progetto e' considerato completo quando:

1. Tutti i criteri di accettazione sopra sono verificati da test automatici
2. `docker run --rm firstance-ts-test` termina con exit code 0
3. `docker run --rm firstance-php-test` termina con exit code 0
4. `docker run --rm firstance-php-test vendor/bin/phpstan analyse src --level=8` termina con exit code 0
5. Coverage TS: statements >= 90%, branches >= 85%
6. `bash tests/cross-language-test.sh` termina con exit code 0
