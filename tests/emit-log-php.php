<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Bper\LambdaObs\Logger\OTelCloudWatchFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$formatter = new OTelCloudWatchFormatter(
    serviceName: 'cross-lang-test',
    serviceVersion: '1.0.0',
    region: '',
);

$handler = new StreamHandler('php://stdout', Level::Info);
$handler->setFormatter($formatter);

$logger = new Logger('cross-lang-test');
$logger->pushHandler($handler);

$logger->info('test log message', ['orderId' => 42, 'status' => 'ok']);
