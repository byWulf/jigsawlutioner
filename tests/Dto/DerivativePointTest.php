<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\DerivativePoint
 */
class DerivativePointTest extends TestCase
{

    public function testIsExtreme(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertFalse($point->isExtreme());

        $point->setExtreme(true);

        $this->assertTrue($point->isExtreme());

        $point->setExtreme(false);

        $this->assertFalse($point->isExtreme());
    }

    public function testGetX(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertEquals(1.5, $point->getX());

        $point->setX(3.5);

        $this->assertEquals(3.5, $point->getX());
    }

    public function testGetY(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertEquals(2.5, $point->getY());

        $point->setY(4.5);

        $this->assertEquals(4.5, $point->getY());
    }

    public function testGetDerivative(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertEquals(30.11, $point->getDerivative());
    }

    public function testGetIndex(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertEquals(2, $point->getIndex());
    }

    public function testIsUsedAsCorner(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertFalse($point->isUsedAsCorner());

        $point->setUsedAsCorner(true);

        $this->assertTrue($point->isUsedAsCorner());

        $point->setUsedAsCorner(false);

        $this->assertFalse($point->isUsedAsCorner());
    }

    public function testJsonSerialize(): void
    {
        $point = new DerivativePoint(1.5, 2.5, 30.11, 2);

        $this->assertEquals([
            'x' => 1.5,
            'y' => 2.5,
            'derivative' => 30.11,
            'index' => 2,
            'extreme' => false,
            'usedAsCorner' => false,
        ], $point->jsonSerialize());
    }
}
