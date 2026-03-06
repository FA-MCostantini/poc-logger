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
        self::ensureServiceName($raw);
        $merged = self::applyEnvOverrides($raw);

        return ConfigSchema::validate($merged);
    }

    private static function findProjectName(): string
    {
        $dir = getcwd() ?: '/';
        while (true) {
            $composerPath = $dir . '/composer.json';
            if (file_exists($composerPath)) {
                $content = file_get_contents($composerPath);
                if ($content !== false) {
                    /** @var mixed $data */
                    $data = json_decode($content, true);
                    if (is_array($data) && isset($data['name']) && is_string($data['name']) && $data['name'] !== '') {
                        $parts = explode('/', $data['name']);
                        return end($parts) ?: 'unknown-service';
                    }
                }
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return 'unknown-service';
    }

    /**
     * @return array<string, mixed>
     */
    private static function getDefaultConfig(string $serviceName): array
    {
        return [
            'service' => ['name' => $serviceName, 'version' => '0.0.0'],
            'logger' => ['level' => 'INFO', 'sampleRate' => 1.0, 'persistentKeys' => []],
            'tracer' => ['enabled' => true, 'captureHTTPS' => true],
            'metrics' => ['namespace' => 'Default', 'captureColdStart' => true],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function ensureServiceName(array &$raw): void
    {
        $service = $raw['service'] ?? null;
        $hasName = is_array($service) && isset($service['name']) && is_string($service['name']) && $service['name'] !== '';
        if (!$hasName) {
            if (!is_array($raw['service'] ?? null)) {
                $raw['service'] = [];
            }
            $raw['service']['name'] = self::findProjectName();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function readYaml(string $filePath): array
    {
        if (!file_exists($filePath)) {
            $serviceName = self::findProjectName();
            $defaultConfig = self::getDefaultConfig($serviceName);
            try {
                $yamlContent = Yaml::dump($defaultConfig, 4, 2);
                $written = @file_put_contents($filePath, $yamlContent);
                if ($written === false) {
                    trigger_error(
                        sprintf('[firstance-obs] Cannot create default config at %s, using in-memory defaults', $filePath),
                        E_USER_WARNING,
                    );
                }
            } catch (\Throwable) {
                trigger_error(
                    sprintf('[firstance-obs] Cannot create default config at %s, using in-memory defaults', $filePath),
                    E_USER_WARNING,
                );
            }

            return $defaultConfig;
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
