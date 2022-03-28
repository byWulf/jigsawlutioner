<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\Point;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Point
 */
class PointTest extends TestCase
{
    public function testGetX(): void
    {
        $point = new Point(1.5, 2.5);

        $this->assertEquals(1.5, $point->getX());

        $point->setX(3.5);

        $this->assertEquals(3.5, $point->getX());
    }

    public function testGetY(): void
    {
        $point = new Point(1.5, 2.5);

        $this->assertEquals(2.5, $point->getY());

        $point->setY(4.5);

        $this->assertEquals(4.5, $point->getY());
    }

    public function testJsonSerialize(): void
    {
        $point = new Point(1.5, 2.5);

        $this->assertEquals([
            'x' => 1.5,
            'y' => 2.5,
        ], $point->jsonSerialize());
    }
}
