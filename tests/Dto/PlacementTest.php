<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Placement
 */
class PlacementTest extends TestCase
{

    public function testGetXAndY(): void
    {
        $placement = new Placement(1, 2, new Piece(1, [], [], 10, 15), 1);

        $this->assertEquals(1, $placement->getX());
        $this->assertEquals(2, $placement->getY());

        $placement
            ->setX(3)
            ->setY(4);

        $this->assertEquals(3, $placement->getX());
        $this->assertEquals(4, $placement->getY());
    }

    public function testGetPiece(): void
    {
        $piece = new Piece(1, [], [], 10, 15);
        $placement = new Placement(1, 2, $piece, 1);

        $this->assertEquals($piece, $placement->getPiece());
    }

    public function testGetTopSideIndex(): void
    {
        $placement = new Placement(1, 2, new Piece(1, [], [], 10, 15), 1);

        $this->assertEquals(1, $placement->getTopSideIndex());

        $placement->setTopSideIndex(2);

        $this->assertEquals(2, $placement->getTopSideIndex());
    }

    public function testGetWidthAndHeight(): void
    {
        $placement = new Placement(1, 2, new Piece(1, [], [
            new Side([], new Point(0, 0), new Point(3, 4)),
            new Side([], new Point(3, 4), new Point(6, 3)),
            new Side([], new Point(6, 3), new Point(0, -3)),
            new Side([], new Point(0, -3), new Point(0, 0)),
        ], 10, 15), 1);

        $this->assertEquals(3.0811388300842, $placement->getWidth());
        $this->assertEquals(6.7426406871193, $placement->getHeight());
    }

    public function testGetContext(): void
    {
        $placement = new Placement(1, 2, new Piece(1, [], [], 10, 15), 1);

        $this->assertNull($placement->getContext());

        $placement->setContext(['a' => 1, 'b' => 2]);

        $this->assertEquals(['a' => 1, 'b' => 2], $placement->getContext());
    }
}
