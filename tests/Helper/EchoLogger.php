<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Helper;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Stringable;

class EchoLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        echo '[' . (new DateTimeImmutable())->format('Y-m-d H:i:s') . '][' . $level . '] ' . $message . ' ';
        if (count($context) > 0) {
            var_export($context);
        }
        echo PHP_EOL;
    }
}
