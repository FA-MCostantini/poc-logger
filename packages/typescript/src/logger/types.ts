export const SEVERITY_MAP = {
  DEBUG: 5,
  INFO: 9,
  WARN: 13,
  ERROR: 17,
} as const;

export type SeverityText = keyof typeof SEVERITY_MAP;

export interface OTelResource {
  readonly 'service.name': string;
  readonly 'service.version': string;
  readonly 'service.language': 'typescript';
  readonly 'faas.name': string;
  readonly 'cloud.provider': 'aws';
  readonly 'cloud.region': string;
}

export interface OTelLogRecord {
  readonly Timestamp: string;
  readonly SeverityText: SeverityText;
  readonly SeverityNumber: number;
  readonly Body: string;
  readonly Resource: OTelResource;
  readonly Attributes: Record<string, unknown>;
  readonly TraceId?: string;
  readonly SpanId?: string;
}
