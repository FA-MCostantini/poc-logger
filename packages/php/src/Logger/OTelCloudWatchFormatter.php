<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

final class OTelCloudWatchFormatter extends JsonFormatter
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $serviceVersion,
        private readonly string $sdkName,
        private readonly string $sdkVersion,
        private readonly string $region,
    ) {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        $severity = Severity::fromMonologLevel($record->level->value);

        $otelRecord = [
            'Timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'SeverityText' => $severity->name,
            'SeverityNumber' => $severity->value,
            'Body' => $record->message,
            'Resource' => [
                'service.name' => $this->serviceName,
                'service.version' => $this->serviceVersion,
                'telemetry.sdk.name' => $this->sdkName,
                'telemetry.sdk.version' => $this->sdkVersion,
                'service.language' => 'php',
                'faas.name' => $record->extra['faas.name'] ?? '',
                'faas.version' => getenv('AWS_LAMBDA_FUNCTION_VERSION') ?: '',
                'faas.memory' => getenv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE') ?: '',
                'faas.instance' => getenv('AWS_LAMBDA_LOG_STREAM_NAME') ?: '',
                'cloud.provider' => 'aws',
                'cloud.region' => $this->region,
                'process.runtime.version' => PHP_VERSION,
            ],
            'Attributes' => array_filter(
                array_merge(
                    [
                        'cold_start' => $record->extra['cold_start'] ?? null,
                        'aws_request_id' => $record->extra['aws_request_id'] ?? null,
                    ],
                    $record->context,
                ),
                static fn (mixed $v): bool => $v !== null,
            ),
        ];

        if (isset($record->extra['trace_id'])) {
            $otelRecord['TraceId'] = $record->extra['trace_id'];
        }

        return $this->toJson($otelRecord) . "\n";
    }
}
