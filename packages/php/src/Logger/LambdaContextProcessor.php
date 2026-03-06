<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class LambdaContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            extra: array_merge($record->extra, [
                'faas.name' => getenv('AWS_LAMBDA_FUNCTION_NAME') ?: '',
                'faas.version' => getenv('AWS_LAMBDA_FUNCTION_VERSION') ?: '',
                'cloud.region' => getenv('AWS_REGION') ?: '',
                'aws_request_id' => getenv('_X_AMZN_REQUEST_ID') ?: '',
                'trace_id' => getenv('_X_AMZN_TRACE_ID') ?: null,
            ]),
        );
    }
}
