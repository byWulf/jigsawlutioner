<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Util;

trait TimeTrackerTrait
{
    private static $minimumTime = [];
    private static $timeSum = [];
    private static $maximumTime = [];
    private static $count = [];

    private function withTimeTracking(callable $callback, string $namespace = 'main'): mixed
    {
        if (!isset(self::$minimumTime[$namespace])) {
            self::$minimumTime[$namespace] = null;
            self::$timeSum[$namespace] = 0;
            self::$maximumTime[$namespace] = null;
            self::$count[$namespace] = 0;
        }

        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $duration = microtime(true) - $start;

            self::$minimumTime[$namespace] = self::$minimumTime[$namespace] === null ? $duration : min(self::$minimumTime[$namespace], $duration);
            self::$timeSum[$namespace] += $duration;
            self::$maximumTime[$namespace] = self::$maximumTime[$namespace] === null ? $duration : max(self::$maximumTime[$namespace], $duration);
            ++self::$count[$namespace];
        }
    }

    public static function getAverageTime(string $namespace = 'main'): float
    {
        if (self::$count[$namespace] === 0) {
            return 0;
        }

        return self::$timeSum[$namespace] / self::$count[$namespace];
    }

    public static function getTimeStatistics(string $namespace = 'main'): string
    {
        if (!isset(self::$minimumTime[$namespace])) {
            return 'No data';
        }

        return sprintf(
            'Times executed: %s // Minimum time: %s // Average time: %s // Maximum time: %s // Time sum: %s',
            self::$count[$namespace],
            self::$minimumTime[$namespace],
            self::$timeSum[$namespace] / self::$count[$namespace],
            self::$maximumTime[$namespace],
            self::$timeSum[$namespace],
        );
    }

    public static function getNamespaces(): array
    {
        return array_keys(self::$minimumTime);
    }

    public static function reset(): void
    {
        self::$minimumTime = [];
        self::$timeSum = [];
        self::$maximumTime = [];
        self::$count = [];
    }
}
