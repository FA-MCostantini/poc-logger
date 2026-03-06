<?php

declare(strict_types=1);

namespace Firstance\LambdaObs;

use Firstance\LambdaObs\Config\ConfigDTO;
use Firstance\LambdaObs\Config\ConfigLoader;
use Firstance\LambdaObs\Logger\ColdStartProcessor;
use Firstance\LambdaObs\Logger\LambdaContextProcessor;
use Firstance\LambdaObs\Logger\OTelCloudWatchFormatter;
use Firstance\LambdaObs\Metrics\EmfMetricsEmitter;
use Firstance\LambdaObs\Tracer\XRayTracerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class FirstanceLoggerFactory
{
    public static function create(string $configPath = './config.yaml'): FirstanceObservability
    {
        $config = ConfigLoader::load($configPath);

        return self::createFromConfig($config);
    }

    public static function createFromConfig(ConfigDTO $config): FirstanceObservability
    {
        $region = getenv('AWS_REGION') ?: '';

        $formatter = new OTelCloudWatchFormatter(
            serviceName: $config->serviceName,
            serviceVersion: $config->serviceVersion,
            region: $region,
        );

        /** @var 'DEBUG'|'INFO'|'WARNING'|'ERROR'|'CRITICAL'|'ALERT'|'EMERGENCY' $levelName */
        $levelName = $config->logLevel === 'WARN' ? 'WARNING' : $config->logLevel;
        $handler = new StreamHandler('php://stdout', Level::fromName($levelName));
        $handler->setFormatter($formatter);

        $logger = new Logger($config->serviceName);
        $logger->pushHandler($handler);
        $logger->pushProcessor(new LambdaContextProcessor());
        $logger->pushProcessor(new ColdStartProcessor());

        $tracer = new XRayTracerFactory($config);
        $metrics = new EmfMetricsEmitter($config);

        return new FirstanceObservability(
            logger: $logger,
            tracer: $tracer,
            metrics: $metrics,
        );
    }
}
