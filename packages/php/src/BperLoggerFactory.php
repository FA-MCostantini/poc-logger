<?php

declare(strict_types=1);

namespace Bper\LambdaObs;

use Bper\LambdaObs\Config\ConfigDTO;
use Bper\LambdaObs\Config\ConfigLoader;
use Bper\LambdaObs\Logger\ColdStartProcessor;
use Bper\LambdaObs\Logger\LambdaContextProcessor;
use Bper\LambdaObs\Logger\OTelCloudWatchFormatter;
use Bper\LambdaObs\Metrics\EmfMetricsEmitter;
use Bper\LambdaObs\Tracer\XRayTracerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class BperLoggerFactory
{
    public static function create(string $configPath = './config.yaml'): BperObservability
    {
        $config = ConfigLoader::load($configPath);

        return self::createFromConfig($config);
    }

    public static function createFromConfig(ConfigDTO $config): BperObservability
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

        return new BperObservability(
            logger: $logger,
            tracer: $tracer,
            metrics: $metrics,
        );
    }
}
