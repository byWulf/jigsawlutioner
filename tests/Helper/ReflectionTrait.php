<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Helper;

use ReflectionClass;

trait ReflectionTrait
{
    protected function overwriteProperty(object $object, string $propertyName, object $newValue): void
    {
        $reflectionClass = new ReflectionClass($object);
        $pointServiceProperty = $reflectionClass->getProperty($propertyName);
        $pointServiceProperty->setValue($object, $newValue);
    }
}
