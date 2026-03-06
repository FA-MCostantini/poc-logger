# Specifiche Requisiti — Firstance Lambda Obs

**Versione**: 1.0.0 | **Data**: 2026-03-06 | **Formato**: EARS (Easy Approach to Requirements Syntax)

Vedere anche: [GLOSSARIO.md](GLOSSARIO.md) | [SCHEMA_REFERENCE.md](SCHEMA_REFERENCE.md) | [ACCEPTANCE_CRITERIA.md](ACCEPTANCE_CRITERIA.md)

---

## Pattern EARS

- **Ubiquitous** — `The <system> shall <action>.`
- **Event-Driven** — `When <trigger>, the <system> shall <action>.`
- **State-Driven** — `While <state>, the <system> shall <action>.`
- **Unwanted Behavior** — `If <condition>, then the <system> shall <action>.`

---

## 1. Configurazione (Config Loading)

**REQ-CFG-01** *(Ubiquitous)*
The ConfigLoader shall parse a YAML file at the path specified by the caller and validate it
against the shared config schema before returning a typed configuration object.

**REQ-CFG-02** *(Event-Driven)*
When an environment variable override is present (`POWERTOOLS_SERVICE_NAME`, `POWERTOOLS_LOG_LEVEL`,
`Firstance_OBS_SAMPLE_RATE`, `Firstance_OBS_METRICS_NAMESPACE`), the ConfigLoader shall apply it on top
of the YAML values before validation, following 12-factor app principles.

**REQ-CFG-03** *(Unwanted Behavior)*
If the YAML file does not exist or is unreadable, then the ConfigLoader shall throw a runtime
exception with a message indicating the missing file path.

**REQ-CFG-04** *(Unwanted Behavior)*
If the parsed YAML does not conform to the config schema (e.g., missing required `service.name`,
invalid log level), then the ConfigLoader shall throw a validation error listing all offending fields.

**REQ-CFG-05** *(Ubiquitous)*
The ConfigLoader shall apply default values for all optional fields:
`service.version = "0.0.0"`, `logger.level = "INFO"`, `logger.sampleRate = 1.0`,
`tracer.enabled = true`, `tracer.captureHTTPS = true`, `metrics.namespace = "Default"`,
`metrics.captureColdStart = true`.

**REQ-CFG-06** *(Ubiquitous)*
The TS and PHP implementations shall accept identical YAML input and produce equivalent
typed configuration objects (cross-language parity).

---

## 2. Formattazione Log (Log Formatting)

**REQ-LOG-01** *(Ubiquitous)*
The OTel log formatter shall produce JSON records with the following top-level keys:
`Timestamp`, `SeverityText`, `SeverityNumber`, `Body`, `Resource`, `Attributes`.

**REQ-LOG-02** *(Event-Driven)*
When an X-Ray trace ID is available in the invocation context, the formatter shall include
a `TraceId` field in the log record.

**REQ-LOG-03** *(Ubiquitous)*
The `Resource` object shall contain: `service.name`, `service.version`, `service.language`,
`faas.name`, `cloud.provider = "aws"`, `cloud.region`.

**REQ-LOG-04** *(Ubiquitous)*
The `Attributes` object shall include `cold_start` and `aws_request_id` from the Lambda context,
plus any additional structured attributes passed by the caller.

**REQ-LOG-05** *(Ubiquitous)*
The `Timestamp` field shall be formatted as ISO 8601 with millisecond precision and UTC timezone
(`YYYY-MM-DDTHH:mm:ss.sssZ`).

**REQ-LOG-06** *(State-Driven)*
While `persistentKeys` are defined in config, the logger shall append them to every log record's
`Attributes` without requiring explicit per-call inclusion.

---

## 3. Mapping Severity (Severity Mapping)

**REQ-SEV-01** *(Ubiquitous)*
The system shall map OTel `SeverityText` to `SeverityNumber` as follows:
`DEBUG` → 5, `INFO` → 9, `WARN` → 13, `ERROR` → 17.

**REQ-SEV-02** *(Event-Driven — PHP)*
When a Monolog log record has level >= 400, the PHP formatter shall map it to `SeverityNumber = 17`
(ERROR), including CRITICAL, ALERT, and EMERGENCY levels.

**REQ-SEV-03** *(Ubiquitous)*
The TS and PHP implementations shall produce identical `SeverityText` and `SeverityNumber`
values for equivalent input log levels.

---

## 4. Propagazione Trace ID (Trace ID Propagation)

**REQ-TRACE-01** *(Event-Driven)*
When the `_X_AMZN_TRACE_ID` environment variable is set, the system shall extract the trace ID
and include it as `TraceId` in the OTel log record.

**REQ-TRACE-02** *(State-Driven — PHP)*
While tracer is enabled, the `XRayTracerFactory` shall provide the current trace ID via
`getTraceId()` for injection into log records by the `LambdaContextProcessor`.

**REQ-TRACE-03** *(Unwanted Behavior)*
If `_X_AMZN_TRACE_ID` is absent or empty, then the `TraceId` field shall be omitted from the
log record (not set to null or empty string).

---

## 5. Rilevamento Cold Start (Cold Start Detection)

**REQ-CS-01** *(Event-Driven — TS)*
When the Lambda handler is invoked for the first time in a container, the Middy metrics middleware
shall automatically emit a `ColdStart` EMF metric with value `1.0`.

**REQ-CS-02** *(Event-Driven — PHP)*
When `EmfMetricsEmitter::emitColdStartMetric(true)` is called, the emitter shall write an EMF
record to STDOUT with metric name `ColdStart`, value `1.0`, unit `Count`.

**REQ-CS-03** *(State-Driven)*
While `metrics.captureColdStart = false` in config, the system shall not emit any `ColdStart` metric.

**REQ-CS-04** *(Ubiquitous)*
The `cold_start` boolean shall be included in `Attributes` of each log record when the Lambda
context is injected.

---

## 6. Emissione Metriche (Metrics Emission)

**REQ-MET-01** *(Ubiquitous)*
The metrics component shall emit CloudWatch metrics via the EMF format written to STDOUT.

**REQ-MET-02** *(Ubiquitous)*
Every EMF record shall include the `service` dimension with the service name from config,
plus any additional dimensions provided by the caller.

**REQ-MET-03** *(Ubiquitous)*
The EMF record shall use the `metrics.namespace` from config as the CloudWatch namespace.

**REQ-MET-04** *(Unwanted Behavior)*
If `json_encode` fails during EMF serialization (PHP), then the emitter shall throw a
`JsonException` to prevent silent data loss.

---

## 7. Middleware Chain (TS)

**REQ-MW-01** *(Ubiquitous)*
The `createMiddlewareChain()` function shall return a single Middy-compatible `MiddlewareLikeObj`
that composes Logger, Tracer, and Metrics middleware.

**REQ-MW-02** *(Ubiquitous)*
The middleware execution order shall be: before phase — Tracer, Logger, Metrics;
after phase — Metrics, Logger, Tracer (reverse); onError — Tracer only.

**REQ-MW-03** *(Event-Driven)*
When `logEvent = true` is passed to `middleware()`, the logger middleware shall log the raw
Lambda event payload at INFO level on each invocation.

---

## 8. Factory / Entry Point

**REQ-FAC-01** *(Ubiquitous — TS)*
The `createFirstanceLogger(options)` function shall return a `FirstanceObservability` object containing
pre-configured `logger`, `tracer`, `metrics` instances and a `middleware()` factory method.

**REQ-FAC-02** *(Ubiquitous — PHP)*
The `FirstanceLoggerFactory` shall provide a static `create(configPath)` method returning a
`FirstanceObservability` value object with `logger`, `tracer`, and `metrics` properties.

**REQ-FAC-03** *(Ubiquitous)*
All instances returned by the factory shall be configured using values from the same
`loadConfig()` call, ensuring consistency between components.

---

## 9. Parita' Cross-Language (Cross-Language Parity)

**REQ-XL-01** *(Ubiquitous)*
Given identical YAML config and equivalent Lambda context, the TS and PHP implementations
shall produce OTel JSON records with the same top-level keys in the same order.

**REQ-XL-02** *(Ubiquitous)*
The cross-language test (`tests/cross-language-test.sh`) shall verify structural parity by
comparing the sorted key sets of TS and PHP OTel output records.

**REQ-XL-03** *(Unwanted Behavior)*
If the TS and PHP key sets differ, then the cross-language test shall exit with code 1
and print a diff of the mismatched keys.
