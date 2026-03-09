import { LogFormatter, LogItem } from '@aws-lambda-powertools/logger';
import type { LogAttributes, UnformattedAttributes } from '@aws-lambda-powertools/logger/types';
import { SEVERITY_MAP } from './types.js';
import type { SeverityText, OTelResource } from './types.js';

interface OTelLogFormatterOptions {
  readonly serviceName: string;
  readonly serviceVersion: string;
  readonly sdkName: string;
  readonly sdkVersion: string;
}

export class OTelLogFormatter extends LogFormatter {
  private readonly serviceName: string;
  private readonly serviceVersion: string;
  private readonly sdkName: string;
  private readonly sdkVersion: string;

  public constructor(options: OTelLogFormatterOptions) {
    super();
    this.serviceName = options.serviceName;
    this.serviceVersion = options.serviceVersion;
    this.sdkName = options.sdkName;
    this.sdkVersion = options.sdkVersion;
  }

  public formatAttributes(
    attributes: UnformattedAttributes,
    additionalLogAttributes: LogAttributes
  ): LogItem {
    const severityText = attributes.logLevel as SeverityText;
    const severityNumber = SEVERITY_MAP[severityText] ?? 0;

    const resource: OTelResource = {
      'service.name': this.serviceName,
      'service.version': this.serviceVersion,
      'telemetry.sdk.name': this.sdkName,
      'telemetry.sdk.version': this.sdkVersion,
      'service.language': 'typescript',
      'faas.name': attributes.lambdaContext?.functionName ?? '',
      'faas.version': process.env['AWS_LAMBDA_FUNCTION_VERSION'] ?? '',
      'faas.memory': process.env['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] ?? '',
      'faas.instance': process.env['AWS_LAMBDA_LOG_STREAM_NAME'] ?? '',
      'cloud.provider': 'aws',
      'cloud.region': attributes.awsRegion,
      'process.runtime.version': process.version.replace(/^v/, ''),
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
