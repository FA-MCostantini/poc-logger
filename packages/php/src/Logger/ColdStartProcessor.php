<?php

declare(strict_types=1);

namespace Firstance\LambdaObs\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class ColdStartProcessor implements ProcessorInterface
{
    private static bool $firstInvocation = true;

    public function __invoke(LogRecord $record): LogRecord
    {
        $isColdStart = self::$firstInvocation;
        self::$firstInvocation = false;

        return $record->with(
            extra: array_merge($record->extra, [
                'cold_start' => $isColdStart,
            ]),
        );
    }

    /**
     * Reset for testing purposes only.
     */
    public static function reset(): void
    {
        self::$firstInvocation = true;
    }
}
