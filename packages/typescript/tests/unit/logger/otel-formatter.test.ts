import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { OTelLogFormatter } from '../../../src/logger/otel-formatter.js';
import type { UnformattedAttributes } from '@aws-lambda-powertools/logger/types';

function makeAttributes(overrides: Partial<UnformattedAttributes> = {}): UnformattedAttributes {
  return {
    message: 'test message',
    logLevel: 'INFO',
    serviceName: 'test-service',
    timestamp: new Date('2026-03-06T10:00:00.000Z'),
    environment: '',
    awsRegion: 'eu-south-1',
    xRayTraceId: '1-abc-def',
    sampleRateValue: 1,
    lambdaContext: {
      functionName: 'my-lambda',
      functionVersion: '$LATEST',
      invokedFunctionArn: 'arn:aws:lambda:eu-south-1:123456:function:my-lambda',
      memoryLimitInMB: 128,
      awsRequestId: 'req-123',
      tenantId: '',
      coldStart: true,
    },
    ...overrides,
  };
}

describe('OTelLogFormatter', () => {
  const originalEnv = { ...process.env };

  beforeEach(() => {
    process.env = { ...originalEnv };
    process.env['AWS_LAMBDA_FUNCTION_VERSION'] = '$LATEST';
    process.env['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] = '512';
    process.env['AWS_LAMBDA_LOG_STREAM_NAME'] = '2026/03/09/[$LATEST]abc123';
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  const formatter = new OTelLogFormatter({
    serviceName: 'test-service',
    serviceVersion: '1.0.0',
    sdkName: 'poc-logger',
    sdkVersion: '0.2.3',
  });

  it('should produce OTel-compliant log record structure', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    expect(output.Timestamp).toBe('2026-03-06T10:00:00.000Z');
    expect(output.SeverityText).toBe('INFO');
    expect(output.SeverityNumber).toBe(9);
    expect(output.Body).toBe('test message');
  });

  it('should include all Resource fields', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;
    expect(resource['service.name']).toBe('test-service');
    expect(resource['service.version']).toBe('1.0.0');
    expect(resource['telemetry.sdk.name']).toBe('poc-logger');
    expect(resource['telemetry.sdk.version']).toBe('0.2.3');
    expect(resource['service.language']).toBe('typescript');
    expect(resource['faas.name']).toBe('my-lambda');
    expect(resource['faas.version']).toBe('$LATEST');
    expect(resource['faas.memory']).toBe('512');
    expect(resource['faas.instance']).toBe('2026/03/09/[$LATEST]abc123');
    expect(resource['cloud.provider']).toBe('aws');
    expect(resource['cloud.region']).toBe('eu-south-1');
    expect(resource['process.runtime.version']).toMatch(/^\d+\.\d+\.\d+/);
  });

  it('should map severity levels correctly', () => {
    const levels = [['DEBUG', 5], ['INFO', 9], ['WARN', 13], ['ERROR', 17]] as const;
    for (const [level, number] of levels) {
      const attrs = makeAttributes({ logLevel: level });
      const logItem = formatter.formatAttributes(attrs, {});
      const output = logItem.getAttributes();
      expect(output.SeverityText).toBe(level);
      expect(output.SeverityNumber).toBe(number);
    }
  });

  it('should include TraceId from X-Ray trace', () => {
    const attrs = makeAttributes({ xRayTraceId: '1-abc-def' });
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    expect(output.TraceId).toBe('1-abc-def');
  });

  it('should merge additional log attributes into Attributes', () => {
    const attrs = makeAttributes();
    const additional = { customKey: 'customValue', orderId: 42 };
    const logItem = formatter.formatAttributes(attrs, additional);
    const output = logItem.getAttributes();
    const attributes = output.Attributes as Record<string, unknown>;
    expect(attributes.customKey).toBe('customValue');
    expect(attributes.orderId).toBe(42);
  });

  it('should include cold_start in Attributes', () => {
    const attrs = makeAttributes();
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const attributes = output.Attributes as Record<string, unknown>;
    expect(attributes.cold_start).toBe(true);
  });

  it('should handle missing lambda context gracefully', () => {
    const attrs = makeAttributes({ lambdaContext: undefined });
    const logItem = formatter.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;
    expect(resource['faas.name']).toBe('');
  });

  it('should default faas env fields to empty string when not set', () => {
    delete process.env['AWS_LAMBDA_FUNCTION_VERSION'];
    delete process.env['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'];
    delete process.env['AWS_LAMBDA_LOG_STREAM_NAME'];
    const f = new OTelLogFormatter({
      serviceName: 'test',
      serviceVersion: '1.0.0',
      sdkName: 'poc-logger',
      sdkVersion: '0.2.3',
    });
    const attrs = makeAttributes();
    const logItem = f.formatAttributes(attrs, {});
    const output = logItem.getAttributes();
    const resource = output.Resource as Record<string, unknown>;
    expect(resource['faas.version']).toBe('');
    expect(resource['faas.memory']).toBe('');
    expect(resource['faas.instance']).toBe('');
  });
});
