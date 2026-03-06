<?php

declare(strict_types=1);

namespace Bper\LambdaObs\Logger;

enum Severity: int
{
    case DEBUG = 5;
    case INFO = 9;
    case WARN = 13;
    case ERROR = 17;

    public static function fromMonologLevel(int $monologLevel): self
    {
        return match (true) {
            $monologLevel >= 500 => self::ERROR,   // CRITICAL, ALERT, EMERGENCY
            $monologLevel >= 400 => self::ERROR,
            $monologLevel >= 300 => self::WARN,    // WARNING
            $monologLevel >= 200 => self::INFO,
            default => self::DEBUG,
        };
    }
}
