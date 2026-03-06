# Schema Reference — Firstance Lambda Obs

**Versione**: 1.0.0 | **Data**: 2026-03-06

Documentazione completa dello schema di configurazione, degli override tramite variabili
d'ambiente, del formato OTel Log Record e del formato EMF metrica.

Vedere anche: [GLOSSARIO.md](GLOSSARIO.md) | [QUERY_REFERENCE.md](QUERY_REFERENCE.md)

---

## 1. Schema `config.yaml`

File di configurazione condiviso tra TS e PHP. Schema JSON completo:
`shared/schemas/config-schema.json`. File di esempio: `shared/config.example.yaml`.

### Sezione `service` (obbligatoria)

| Campo | Tipo | Default | Descrizione |
|---|---|---|---|
| `service.name` | `string` (min 1) | — | Nome del servizio. Usato in `Resource['service.name']` e come dimensione EMF. |
| `service.version` | `string` | `"0.0.0"` | Versione semver. Usato in `Resource['service.version']`. |

### Sezione `logger` (opzionale)

| Campo | Tipo | Default | Descrizione |
|---|---|---|---|
| `logger.level` | `DEBUG\|INFO\|WARN\|ERROR` | `"INFO"` | Livello minimo di log. Override: `POWERTOOLS_LOG_LEVEL`. |
| `logger.sampleRate` | `number` [0.0–1.0] | `1.0` | Percentuale di invocazioni in cui vengono emessi i log DEBUG. Override: `Firstance_OBS_SAMPLE_RATE`. |
| `logger.persistentKeys` | `object<string,string>` | `{}` | Coppie chiave-valore aggiunte a ogni record in `Attributes`. |

### Sezione `tracer` (opzionale)

| Campo | Tipo | Default | Descrizione |
|---|---|---|---|
| `tracer.enabled` | `boolean` | `true` | Abilita/disabilita il tracing X-Ray. |
| `tracer.captureHTTPS` | `boolean` | `true` | Auto-capture delle chiamate HTTPS uscenti (TS: Powertools Tracer). |

### Sezione `metrics` (opzionale)

| Campo | Tipo | Default | Descrizione |
|---|---|---|---|
| `metrics.namespace` | `string` | `"Default"` | Namespace CloudWatch Metrics. Override: `Firstance_OBS_METRICS_NAMESPACE`. |
| `metrics.captureColdStart` | `boolean` | `true` | Emette la metrica `ColdStart` automaticamente. |

### Esempio completo

```yaml
service:
  name: "firstance-file-delivery"
  version: "1.0.0"

logger:
  level: "INFO"
  sampleRate: 0.1
  persistentKeys:
    team: "integrations"
    partner: "athora"

tracer:
  enabled: true
  captureHTTPS: true

metrics:
  namespace: "FirstanceFileDelivery"
  captureColdStart: true
```

---

## 2. Environment Variable Overrides

Seguendo il principio 12-factor, le variabili d'ambiente hanno priorita' sul file YAML.
L'override avviene dopo il parsing YAML e prima della validazione schema.

| Variabile | Campo YAML | Tipo | Esempio |
|---|---|---|---|
| `POWERTOOLS_SERVICE_NAME` | `service.name` | `string` | `"firstance-payment-gateway"` |
| `POWERTOOLS_LOG_LEVEL` | `logger.level` | `DEBUG\|INFO\|WARN\|ERROR` | `"DEBUG"` |
| `Firstance_OBS_SAMPLE_RATE` | `logger.sampleRate` | `float` (stringa → float) | `"0.05"` |
| `Firstance_OBS_METRICS_NAMESPACE` | `metrics.namespace` | `string` | `"FirstancePayments"` |

**Variabili di runtime Lambda** (non gestite dal ConfigLoader, lette direttamente):

| Variabile | Utilizzo |
|---|---|
| `_X_AMZN_TRACE_ID` | Propagazione Trace ID nel record OTel |
| `AWS_REGION` | Campo `Resource['cloud.region']` |

---

## 3. Formato OTel Log Record

Ogni log emesso su STDOUT da TS (`OTelLogFormatter`) o PHP (`OTelCloudWatchFormatter`)
produce un JSON single-line con questa struttura:

```json
{
  "Timestamp": "2026-03-06T10:30:00.123Z",
  "SeverityText": "INFO",
  "SeverityNumber": 9,
  "Body": "Documento elaborato con successo",
  "Resource": {
    "service.name": "firstance-file-delivery",
    "service.version": "1.0.0",
    "service.language": "typescript",
    "faas.name": "firstance-file-delivery-prod",
    "cloud.provider": "aws",
    "cloud.region": "eu-west-1"
  },
  "Attributes": {
    "cold_start": false,
    "aws_request_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "orderId": "ORD-2026-001",
    "team": "integrations"
  },
  "TraceId": "Root=1-67c9a400-abcdef1234567890abcdef12;Parent=abcdef1234567890;Sampled=1"
}
```

### Descrizione campi

| Campo | Tipo | Obbligatorio | Descrizione |
|---|---|---|---|
| `Timestamp` | `string` (ISO 8601 UTC, ms) | Si' | Istante di emissione del log. |
| `SeverityText` | `DEBUG\|INFO\|WARN\|ERROR` | Si' | Livello testuale OTel. |
| `SeverityNumber` | `5\|9\|13\|17` | Si' | Livello numerico OTel. Usato per query per soglia. |
| `Body` | `string` | Si' | Messaggio di log. |
| `Resource` | `object` | Si' | Attributi del servizio/runtime (statici per invocazione). |
| `Resource['service.language']` | `"typescript"\|"php"` | Si' | Differenzia TS e PHP in query cross-language. |
| `Attributes` | `object` | Si' | Attributi contestuali (Lambda context + custom). |
| `Attributes.cold_start` | `boolean` | No | Presente quando Lambda context e' iniettato. |
| `Attributes.aws_request_id` | `string` | No | ID invocazione Lambda. |
| `TraceId` | `string` | No | X-Ray trace header. Omesso se `_X_AMZN_TRACE_ID` non e' presente. |

---

## 4. Formato EMF Metrica

Emesso da `Metrics` (TS, via Powertools) e `EmfMetricsEmitter::putMetric()` (PHP) su STDOUT.

```json
{
  "_aws": {
    "Timestamp": 1741257000123,
    "CloudWatchMetrics": [
      {
        "Namespace": "FirstanceFileDelivery",
        "Dimensions": [["service", "partner"]],
        "Metrics": [
          {"Name": "DocumentsProcessed", "Unit": "Count"}
        ]
      }
    ]
  },
  "service": "firstance-file-delivery",
  "partner": "athora",
  "DocumentsProcessed": 42.0
}
```

### Record ColdStart (auto-emesso)

```json
{
  "_aws": {
    "Timestamp": 1741257000000,
    "CloudWatchMetrics": [
      {
        "Namespace": "FirstanceFileDelivery",
        "Dimensions": [["service"]],
        "Metrics": [{"Name": "ColdStart", "Unit": "Count"}]
      }
    ]
  },
  "service": "firstance-file-delivery",
  "ColdStart": 1.0
}
```

### Unita' metrica supportate (CloudWatch standard)

`Count`, `Seconds`, `Milliseconds`, `Microseconds`, `Bytes`, `Kilobytes`, `Megabytes`,
`Gigabytes`, `Terabytes`, `Bits`, `Kilobits`, `Megabits`, `Gigabits`, `Terabits`,
`Percent`, `None`
