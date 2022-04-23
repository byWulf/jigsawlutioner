<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Helper;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Stringable;

class NullLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void {}

    public function alert($message, array $context = []): void {}

    public function critical($message, array $context = []): void {}

    public function error($message, array $context = []): void {}

    public function warning($message, array $context = []): void {}

    public function notice($message, array $context = []): void {}

    public function info($message, array $context = []): void {}

    public function debug($message, array $context = []): void {}

    public function log($level, $message, array $context = []): void {}
}
