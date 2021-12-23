<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Util;

trait TimeTrackerTrait
{
    private static $time = 0;
    private static $count = 0;

    private function withTimeTracking(callable $callback): mixed
    {
        $start = microtime(true);

        $result = $callback();

        self::$time += (microtime(true) - $start);
        ++self::$count;

        return $result;
    }

    public static function getAverageTime(): float
    {
        if (self::$count === 0) {
            return 0;
        }

        return self::$time / self::$count;
    }

    public static function reset(): void
    {
        self::$time = 0;
        self::$count = 0;
    }
}
