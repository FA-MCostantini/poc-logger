<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

final class ConfigSchema
{
    private const VALID_LOG_LEVELS = ['DEBUG', 'INFO', 'WARN', 'ERROR'];

    /**
     * @param array<string, mixed> $raw
     */
    public static function validate(array $raw): ConfigDTO
    {
        $logger = is_array(self::arrayGet($raw, 'logger', [])) ? self::arrayGet($raw, 'logger', []) : [];
        $logLevel = self::stringOrDefault($logger, 'level', 'INFO');
        if (!in_array($logLevel, self::VALID_LOG_LEVELS, true)) {
            throw new \InvalidArgumentException(
                sprintf('logger.level must be one of %s, got "%s"', implode(', ', self::VALID_LOG_LEVELS), $logLevel)
            );
        }

        $sampleRate = self::floatOrDefault($logger, 'sampleRate', 1.0);
        if ($sampleRate < 0.0 || $sampleRate > 1.0) {
            throw new \InvalidArgumentException('logger.sampleRate must be between 0.0 and 1.0');
        }

        $persistentKeys = self::arrayGet($logger, 'persistentKeys', []);
        if (!is_array($persistentKeys)) {
            $persistentKeys = [];
        }

        $tracer = is_array(self::arrayGet($raw, 'tracer', [])) ? self::arrayGet($raw, 'tracer', []) : [];
        $tracerEnabled = self::boolOrDefault($tracer, 'enabled', true);
        $tracerCaptureHTTPS = self::boolOrDefault($tracer, 'captureHTTPS', true);

        $metrics = is_array(self::arrayGet($raw, 'metrics', [])) ? self::arrayGet($raw, 'metrics', []) : [];
        $metricsNamespace = self::stringOrDefault($metrics, 'namespace', 'Default');
        $metricsCaptureColdStart = self::boolOrDefault($metrics, 'captureColdStart', true);

        return new ConfigDTO(
            logLevel: $logLevel,
            logSampleRate: $sampleRate,
            persistentKeys: $persistentKeys,
            tracerEnabled: $tracerEnabled,
            tracerCaptureHTTPS: $tracerCaptureHTTPS,
            metricsNamespace: $metricsNamespace,
            metricsCaptureColdStart: $metricsCaptureColdStart,
        );
    }

    /**
     * @param array<string, mixed> $array
     */
    private static function arrayGet(array $array, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    /**
     * @param array<string, mixed> $array
     */
    private static function stringOrDefault(array $array, string $key, string $default): string
    {
        $value = self::arrayGet($array, $key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $array
     */
    private static function floatOrDefault(array $array, string $key, float $default): float
    {
        $value = self::arrayGet($array, $key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param array<string, mixed> $array
     */
    private static function boolOrDefault(array $array, string $key, bool $default): bool
    {
        $value = self::arrayGet($array, $key, $default);
        return is_bool($value) ? $value : $default;
    }
}
