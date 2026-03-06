<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    private const ENV_MAPPINGS = [
        'POWERTOOLS_SERVICE_NAME' => ['service', 'name'],
        'POWERTOOLS_LOG_LEVEL' => ['logger', 'level'],
        'Firstance_OBS_SAMPLE_RATE' => ['logger', 'sampleRate'],
        'Firstance_OBS_METRICS_NAMESPACE' => ['metrics', 'namespace'],
    ];

    private const FLOAT_KEYS = ['Firstance_OBS_SAMPLE_RATE'];

    public static function load(string $configPath): ConfigDTO
    {
        $raw = self::readYaml($configPath);
        $merged = self::applyEnvOverrides($raw);

        return ConfigSchema::validate($merged);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readYaml(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException(sprintf('Config file not found: %s', $filePath));
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read config file: %s', $filePath));
        }

        $parsed = Yaml::parse($content);
        if (!is_array($parsed)) {
            throw new \RuntimeException(sprintf('Invalid YAML content in %s', $filePath));
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function applyEnvOverrides(array $config): array
    {
        foreach (self::ENV_MAPPINGS as $envKey => $path) {
            $envValue = getenv($envKey);
            if ($envValue === false) {
                continue;
            }

            $value = in_array($envKey, self::FLOAT_KEYS, true)
                ? (float) $envValue
                : $envValue;

            self::setNestedValue($config, $path, $value);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $path
     */
    private static function setNestedValue(array &$array, array $path, mixed $value): void
    {
        $current = &$array;
        for ($i = 0; $i < count($path) - 1; $i++) {
            $key = $path[$i];
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current[$path[count($path) - 1]] = $value;
    }
}
