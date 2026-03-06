import { LogFormatter, LogItem } from '@aws-lambda-powertools/logger';
import type { LogAttributes, UnformattedAttributes } from '@aws-lambda-powertools/logger/types';
import { SEVERITY_MAP } from './types.js';
import type { SeverityText, OTelResource } from './types.js';

interface OTelLogFormatterOptions {
  readonly serviceVersion: string;
}

export class OTelLogFormatter extends LogFormatter {
  private readonly serviceVersion: string;

  public constructor(options: OTelLogFormatterOptions) {
    super();
    this.serviceVersion = options.serviceVersion;
  }

  public formatAttributes(
    attributes: UnformattedAttributes,
    additionalLogAttributes: LogAttributes
  ): LogItem {
    const severityText = attributes.logLevel as SeverityText;
    const severityNumber = SEVERITY_MAP[severityText] ?? 0;

    const resource: OTelResource = {
      'service.name': attributes.serviceName,
      'service.version': this.serviceVersion,
      'service.language': 'typescript',
      'faas.name': attributes.lambdaContext?.functionName ?? '',
      'cloud.provider': 'aws',
      'cloud.region': attributes.awsRegion,
    };

    const logRecord: LogAttributes = {
      Timestamp: this.formatTimestamp(attributes.timestamp),
      SeverityText: severityText,
      SeverityNumber: severityNumber,
      Body: attributes.message,
      Resource: resource as unknown as LogAttributes,
      Attributes: {
        cold_start: attributes.lambdaContext?.coldStart,
        aws_request_id: attributes.lambdaContext?.awsRequestId,
        ...additionalLogAttributes,
      } as LogAttributes,
      ...(attributes.xRayTraceId ? { TraceId: attributes.xRayTraceId } : {}),
    };

    return new LogItem({ attributes: logRecord });
  }
}
