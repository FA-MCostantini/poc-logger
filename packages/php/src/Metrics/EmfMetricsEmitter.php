<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Metrics;

use Firstance\LambdaObs\Config\ConfigDTO;

final class EmfMetricsEmitter
{
    /** @var resource */
    private $outputStream;

    /**
     * @param resource|null $outputStream Override for testing (defaults to STDOUT)
     */
    public function __construct(
        private readonly ConfigDTO $config,
        $outputStream = null,
    ) {
        $this->outputStream = $outputStream ?? STDOUT;
    }

    /**
     * @param array<string, string> $dimensions
     */
    public function putMetric(
        string $name,
        float $value,
        string $unit = 'Count',
        array $dimensions = [],
    ): void {
        $allDimensions = array_merge(
            ['service' => $this->config->serviceName],
            $dimensions,
        );

        $dimensionKeys = array_keys($allDimensions);

        $emfRecord = array_merge(
            [
                '_aws' => [
                    'Timestamp' => (int) (microtime(true) * 1000),
                    'CloudWatchMetrics' => [
                        [
                            'Namespace' => $this->config->metricsNamespace,
                            'Dimensions' => [$dimensionKeys],
                            'Metrics' => [
                                ['Name' => $name, 'Unit' => $unit],
                            ],
                        ],
                    ],
                ],
            ],
            $allDimensions,
            [$name => $value],
        );

        fwrite($this->outputStream, json_encode($emfRecord, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) . "\n");
    }

    public function emitColdStartMetric(bool $isColdStart): void
    {
        if (!$this->config->metricsCaptureColdStart) {
            return;
        }

        $this->putMetric('ColdStart', $isColdStart ? 1.0 : 0.0);
    }
}
