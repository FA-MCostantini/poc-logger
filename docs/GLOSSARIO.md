# Glossario — Firstance Lambda Obs

**Versione**: 1.0.0 | **Data**: 2026-03-06

Glossario dei termini tecnici usati nel progetto. Vedere anche:
[SPEC_REQUISITI.md](SPEC_REQUISITI.md) | [SCHEMA_REFERENCE.md](SCHEMA_REFERENCE.md)

---

## OTel / OpenTelemetry

Standard open-source per l'osservabilita' distribuita (CNCF). Definisce API, SDK e formati dati
per log, trace e metriche indipendenti dal vendor. In questo progetto viene adottato il formato
**OTel Log Record** come struttura JSON emessa su CloudWatch Logs.

Riferimento: https://opentelemetry.io/docs/specs/otel/logs/data-model/

---

## EMF (Embedded Metric Format)

Formato JSON proprietario AWS per emettere metriche CloudWatch direttamente nei log.
Un record EMF contiene la chiave `_aws.CloudWatchMetrics` con namespace, dimensioni e
definizioni di metrica; i valori si trovano al top-level del documento.

CloudWatch Logs analizza automaticamente i record EMF e li inserisce come metriche CloudWatch,
eliminando la necessita' di chiamate dirette alla CloudWatch Metrics API.

Esempio minimo:
```json
{
  "_aws": {
    "Timestamp": 1709712000000,
    "CloudWatchMetrics": [{
      "Namespace": "FirstanceFileDelivery",
      "Dimensions": [["service"]],
      "Metrics": [{"Name": "ColdStart", "Unit": "Count"}]
    }]
  },
  "service": "firstance-file-delivery",
  "ColdStart": 1.0
}
```

---

## X-Ray

Servizio AWS per il distributed tracing. Lambda inietta l'header `_X_AMZN_TRACE_ID` in ogni
invocazione; il valore viene propagato come `TraceId` nel record OTel per correlare log e trace.

Formato header: `Root=1-xxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxx;Parent=xxxxxxxxxxxxxxxx;Sampled=1`

---

## Cold Start

Condizione in cui il runtime Lambda deve inizializzare il container prima di eseguire il handler.
Aumenta la latenza della prima invocazione dopo un periodo di inattivita' o dopo un deploy.

In questo progetto:
- **TS**: `Metrics.logMetrics({ captureColdStartMetric: true })` via Middy emette la metrica automaticamente
- **PHP**: `EmfMetricsEmitter::emitColdStartMetric(bool)` viene chiamato esplicitamente nel handler
- Il flag `cold_start` appare anche in `Attributes` del record OTel log

---

## Severity (livelli OTel)

Mapping tra livello testuale e SeverityNumber secondo la specifica OTel Logs:

| SeverityText | SeverityNumber | Monolog Level (PHP) |
|---|---|---|
| `DEBUG` | `5` | 100 |
| `INFO` | `9` | 200 |
| `WARN` | `13` | 300 |
| `ERROR` | `17` | 400, 500+ |

Il campo `SeverityNumber` e' fondamentale per le query CloudWatch Logs Insights che filtrano
per soglia numerica (es. tutti i record con `SeverityNumber >= 13`).

---

## CloudWatch Logs Insights

Motore di query interattivo per CloudWatch Logs. Supporta sintassi propria con `fields`, `filter`,
`stats`, `sort`, `limit`. Ottimale per analizzare log strutturati JSON.

Query di riferimento: [QUERY_REFERENCE.md](QUERY_REFERENCE.md)

---

## Middy

Framework middleware per AWS Lambda Node.js (`@middy/core`). Permette di applicare
cross-cutting concerns (logging, tracing, metrics) in modo dichiarativo attorno al handler.

In questo progetto `createMiddlewareChain()` compone tre middleware Powertools nell'ordine:

- **before**: Tracer → Logger → Metrics
- **after**: Metrics → Logger → Tracer (ordine inverso)
- **onError**: solo Tracer (per annotare il segmento X-Ray con l'errore)

---

## Monolog

Libreria PHP de-facto per il logging (`monolog/monolog ^3`). Usa `Handler` + `Formatter` per
trasformare i `LogRecord` in output. In questo progetto `OTelCloudWatchFormatter` estende
`JsonFormatter` per emettere il formato OTel su STDOUT (che Lambda invia a CloudWatch Logs).

Il mapping dei livelli Monolog verso OTel Severity e' gestito dalla enum `Severity`.

---

## Powertools for AWS Lambda

Suite di utility AWS per Lambda disponibile in TypeScript (`@aws-lambda-powertools/*`) e Python.
In questo progetto vengono usati tre moduli:

- `@aws-lambda-powertools/logger` — Logger con `LogFormatter` personalizzabile
- `@aws-lambda-powertools/tracer` — Wrapper X-Ray SDK con auto-capture
- `@aws-lambda-powertools/metrics` — Emissione EMF con gestione cold start

---

## PSR-3

Standard PHP-FIG per le interfacce di logging (`Psr\Log\LoggerInterface`). Definisce i metodi
`debug()`, `info()`, `warning()`, `error()`, `critical()`, ecc. Monolog implementa PSR-3.
Il progetto PHP usa Monolog direttamente (non l'interfaccia PSR-3) per accedere alle feature
specifiche di Monolog 3 (processors, formatters, handlers).
