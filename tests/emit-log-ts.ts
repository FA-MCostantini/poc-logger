import { Logger } from '@aws-lambda-powertools/logger';
import { OTelLogFormatter } from './packages/typescript/src/logger/otel-formatter.js';

const formatter = new OTelLogFormatter({ serviceVersion: '1.0.0' });

const logger = new Logger({
  serviceName: 'cross-lang-test',
  logLevel: 'INFO',
  logFormatter: formatter,
});

logger.info('test log message', { orderId: 42, status: 'ok' });
