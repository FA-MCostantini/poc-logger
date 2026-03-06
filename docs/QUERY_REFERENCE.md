# Query Reference — CloudWatch Logs Insights

**Versione**: 1.0.0 | **Data**: 2026-03-06

Raccolta di query pronte per CloudWatch Logs Insights. Tutte le query assumono il formato
OTel Log Record descritto in [SCHEMA_REFERENCE.md](SCHEMA_REFERENCE.md).

Sintassi di riferimento: [GLOSSARIO.md](GLOSSARIO.md#cloudwatch-logs-insights)

---

## Come usare queste query

1. Aprire **CloudWatch > Logs Insights** nella console AWS
2. Selezionare il Log Group del Lambda target
3. Impostare il time range desiderato
4. Incollare la query e cliccare **Run query**

I campi JSON nei log strutturati sono accessibili direttamente per nome (es. `SeverityNumber`).
Per i campi annidati usare la dot notation (es. `Resource.service_name`).

**Nota**: CloudWatch Logs Insights converte automaticamente i punti nei nomi delle chiavi JSON
in underscore. `Resource['service.name']` diventa `Resource.service_name` nelle query.

---

## Query 1 — Filtra per livello di severity (soglia)

Mostra tutti i record con severity ERROR o superiore.

```
fields Timestamp, SeverityText, Body, Attributes.aws_request_id
| filter SeverityNumber >= 17
| sort Timestamp desc
| limit 100
```

---

## Query 2 — Filtra per service name

Tutti i log di un servizio specifico (utile quando piu' Lambda condividono lo stesso Log Group).

```
fields Timestamp, SeverityText, Body
| filter Resource.service_name = "firstance-file-delivery"
| sort Timestamp desc
| limit 200
```

---

## Query 3 — Filtra cold start

Invocazioni che hanno avuto un cold start (primo avvio del container).

```
fields Timestamp, Attributes.aws_request_id, Resource.faas_name
| filter Attributes.cold_start = 1
| sort Timestamp desc
| limit 50
```

---

## Query 4 — Errori nell'ultima ora

Tutti gli errori ordinati per recency. Impostare il time range a "Last 1 hour" nell'UI.

```
fields Timestamp, Body, Attributes.aws_request_id, TraceId
| filter SeverityNumber >= 17
| sort Timestamp desc
| limit 200
```

---

## Query 5 — Raggruppa per Trace ID

Tutti i log appartenenti a una singola traccia X-Ray, utile per debug di una richiesta.

```
fields Timestamp, SeverityText, Body, Attributes.aws_request_id
| filter TraceId = "Root=1-XXXXXXXX-XXXXXXXXXXXXXXXXXXXXXXXX;Parent=XXXXXXXXXXXXXXXX;Sampled=1"
| sort Timestamp asc
```

Sostituire il valore di `TraceId` con quello trovato in X-Ray o da un log di errore.

---

## Query 6 — Conta per severity level

Distribuzione dei log per livello nell'intervallo selezionato.

```
stats count(*) as occurrenze by SeverityText
| sort occurrenze desc
```

---

## Query 7 — Filtra per attributo custom (orderId)

Ricerca di tutti i log relativi a un ordine specifico.

```
fields Timestamp, SeverityText, Body, Attributes.aws_request_id
| filter Attributes.orderId = "ORD-2026-001"
| sort Timestamp asc
```

Per cercare per pattern (prefisso):
```
fields Timestamp, SeverityText, Body
| filter Attributes.orderId like /^ORD-2026/
| sort Timestamp desc
| limit 100
```

---

## Query 8 — Filtra per function name (faas.name)

Log di una specifica Lambda function (utile se il Log Group e' condiviso o aggregato).

```
fields Timestamp, SeverityText, Body
| filter Resource.faas_name = "firstance-file-delivery-prod"
| sort Timestamp desc
| limit 100
```

---

## Query 9 — Analisi latenza (durata invocazioni)

Richiede che il handler loggi la durata al completamento. Calcola statistiche di latenza.

```
fields @timestamp, Attributes.duration_ms, Attributes.aws_request_id
| filter ispresent(Attributes.duration_ms)
| stats
    avg(Attributes.duration_ms) as avg_ms,
    pct(Attributes.duration_ms, 95) as p95_ms,
    pct(Attributes.duration_ms, 99) as p99_ms,
    max(Attributes.duration_ms) as max_ms
  by bin(5m)
| sort @timestamp desc
```

---

## Query 10 — Correlazione cross-service per Trace ID

Trova tutti i log di diversi servizi che condividono lo stesso Trace ID,
utile per analizzare una richiesta che attraversa piu' Lambda.

```
fields Timestamp, Resource.service_name, Resource.service_language, SeverityText, Body
| filter ispresent(TraceId)
| filter TraceId like /Root=1-XXXXXXXX/
| sort Timestamp asc
```

---

## Query 11 — Errori per servizio nell'intervallo

Conta gli errori per servizio per identificare i sistemi piu' critici.

```
stats count(*) as errori by Resource.service_name
| filter SeverityNumber >= 17
| sort errori desc
```

---

## Query 12 — Paragone output TS vs PHP (cross-language debug)

Utile durante il test di parita' per confrontare output delle due implementazioni.

```
fields Timestamp, Resource.service_language, SeverityText, SeverityNumber, Body
| filter Resource.service_name = "firstance-file-delivery"
| sort Timestamp desc, Resource.service_language asc
| limit 50
```

---

## Query 13 — Cold start rate per funzione (ultime 24h)

Percentuale di cold start rispetto alle invocazioni totali. Richiede query separata
sulle metriche EMF oppure sui log con `cold_start` in `Attributes`.

```
stats
    count(*) as total_invocations,
    sum(Attributes.cold_start) as cold_starts
  by Resource.faas_name
| fields cold_starts / total_invocations * 100 as cold_start_pct
| sort cold_start_pct desc
```
