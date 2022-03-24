<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Group
 */
class GroupTest extends TestCase
{
    public function testGetPlacements(): void
    {
        $group = new Group();

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(1, 2, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 2, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals([$placement1, $placement2, $placement3], $group->getPlacements());
    }

    public function testGetPlacementByPosition(): void
    {
        $group = new Group();

        $this->assertEquals(null, $group->getLastPlacementByPosition(1, 2));

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(1, 2, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 2, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals($placement3, $group->getLastPlacementByPosition(1, 2));
    }

    public function testGetPlacementByPositionNotUnique(): void
    {
        $group = new Group();

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 2, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->expectException(LogicException::class);
        $group->getPlacementByPosition(1, 2);
    }

    public function testGetPlacementsByPosition(): void
    {
        $group = new Group();

        $this->assertEquals([], $group->getPlacementsByPosition(1, 2));

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 2, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals([$placement1, $placement3], $group->getPlacementsByPosition(1, 2));
    }

    public function testGetFirstPlacement(): void
    {
        $group = new Group();

        $this->assertNull($group->getFirstPlacement());

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(3, 4, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals($placement1, $group->getFirstPlacement());
    }

    public function testGetFirstPlacementByPosition(): void
    {
        $group = new Group();

        $this->assertEquals(null, $group->getFirstPlacementByPosition(1, 2));

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(1, 2, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 2, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals($placement1, $group->getFirstPlacementByPosition(1, 2));
    }

    public function testGetLastPlacementByPosition(): void
    {
        $group = new Group();

        $this->assertEquals(null, $group->getLastPlacementByPosition(1, 2));

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(1, 2, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 2, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals($placement3, $group->getLastPlacementByPosition(1, 2));
    }

    public function testGetPlacementByPiece(): void
    {
        $group = new Group();

        $piece3 = new Piece(3, [], [], 3, 3);

        $this->assertNull($group->getPlacementByPiece($piece3));

        $placement1 = new Placement(1, 2, $piece3, 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(3, 4, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals($placement1, $group->getPlacementByPiece($piece3));
    }

    public function testGetPlacementsGroupedByPosition(): void
    {
        $group = new Group();

        $this->assertEquals([], $group->getPlacementsGroupedByPosition());

        $placement1 = new Placement(1, 2, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 2, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(1, 4, new Piece(5, [], [], 5, 5), 0);
        $placement4 = new Placement(1, 4, new Piece(6, [], [], 6, 6), 1);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);
        $group->addPlacement($placement4);

        $this->assertEquals([
            2 => [1 => [$placement1], 2 => [$placement2]],
            4 => [1 => [$placement3, $placement4]],
        ], $group->getPlacementsGroupedByPosition());
    }

    public function testRemovePlacements(): void
    {
        $group = new Group();

        $piece3 = new Piece(3, [], [], 3, 3);
        $placement1 = new Placement(1, 2, $piece3, 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 5, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals([$placement1, $placement2, $placement3], $group->getPlacements());
        $this->assertEquals($placement1, $group->getPlacementByPiece($piece3));
        $this->assertEquals($placement1, $group->getPlacementByPosition(1, 2));

        $group->removePlacements([$placement1, $placement3]);

        $this->assertEquals([$placement2], array_values($group->getPlacements()));
        $this->assertEquals(null, $group->getPlacementByPiece($piece3));
        $this->assertEquals(null, $group->getPlacementByPosition(1, 2));
    }

    public function testRemovePlacement(): void
    {
        $group = new Group();

        $piece3 = new Piece(3, [], [], 3, 3);
        $placement1 = new Placement(1, 2, $piece3, 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 5, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals([$placement1, $placement2, $placement3], $group->getPlacements());
        $this->assertEquals($placement1, $group->getPlacementByPiece($piece3));
        $this->assertEquals($placement1, $group->getPlacementByPosition(1, 2));

        $group->removePlacement($placement1);

        $this->assertEquals([$placement2, $placement3], array_values($group->getPlacements()));
        $this->assertEquals(null, $group->getPlacementByPiece($piece3));
        $this->assertEquals(null, $group->getPlacementByPosition(1, 2));
    }

    public function testAddPlacementsFromGroup(): void
    {
        $group1 = new Group();
        $group2 = new Group();

        $piece3 = new Piece(3, [], [], 3, 3);
        $placement1 = new Placement(1, 2, $piece3, 2);
        $placement2 = new Placement(2, 3, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 5, new Piece(5, [], [], 5, 5), 0);
        $group1->addPlacement($placement1);
        $group1->addPlacement($placement2);
        $group1->addPlacement($placement3);

        $this->assertEquals([], $group2->getPlacements());
        $this->assertEquals(null, $group2->getPlacementByPiece($piece3));
        $this->assertEquals(null, $group2->getPlacementByPosition(1, 2));

        $group2->addPlacementsFromGroup($group1);

        $this->assertEquals([$placement1, $placement2, $placement3], $group2->getPlacements());
        $this->assertEquals($placement1, $group2->getPlacementByPiece($piece3));
        $this->assertEquals($placement1, $group2->getPlacementByPosition(1, 2));
    }

    public function testGetWidthAndHeight(): void
    {
        $group = new Group();

        $this->assertEquals(0, $group->getWidth());
        $this->assertEquals(0, $group->getHeight());

        $piece3 = new Piece(3, [], [], 3, 3);
        $placement1 = new Placement(1, 4, $piece3, 2);
        $placement2 = new Placement(2, 6, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 1, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $this->assertEquals(4, $group->getWidth());
        $this->assertEquals(6, $group->getHeight());
    }

    public function testRotate(): void
    {
        $group = new Group();

        $placement1 = new Placement(1, 4, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 6, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 1, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $group->rotate(1);

        $this->assertEquals(-4, $placement1->getX());
        $this->assertEquals(1, $placement1->getY());
        $this->assertEquals(-6, $placement2->getX());
        $this->assertEquals(2, $placement2->getY());
        $this->assertEquals(-1, $placement3->getX());
        $this->assertEquals(4, $placement3->getY());

        $group->rotate(-1);

        $this->assertEquals(1, $placement1->getX());
        $this->assertEquals(4, $placement1->getY());
        $this->assertEquals(2, $placement2->getX());
        $this->assertEquals(6, $placement2->getY());
        $this->assertEquals(4, $placement3->getX());
        $this->assertEquals(1, $placement3->getY());
    }

    public function testMove(): void
    {
        $group = new Group();

        $placement1 = new Placement(1, 4, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 6, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 1, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $group->move(3, -2);

        $this->assertEquals(4, $placement1->getX());
        $this->assertEquals(2, $placement1->getY());
        $this->assertEquals(5, $placement2->getX());
        $this->assertEquals(4, $placement2->getY());
        $this->assertEquals(7, $placement3->getX());
        $this->assertEquals(-1, $placement3->getY());
    }

    public function testClone(): void
    {
        $group = new Group();

        $placement1 = new Placement(1, 4, new Piece(3, [], [], 3, 3), 2);
        $placement2 = new Placement(2, 6, new Piece(4, [], [], 4, 4), 3);
        $placement3 = new Placement(4, 1, new Piece(5, [], [], 5, 5), 0);
        $group->addPlacement($placement1);
        $group->addPlacement($placement2);
        $group->addPlacement($placement3);

        $group2 = clone $group;
        $clonedPlacement1 = $group2->getPlacementByPosition(1, 4);

        $group->move(3, -2);

        $this->assertEquals(4, $placement1->getX());
        $this->assertEquals(2, $placement1->getY());

        $this->assertEquals(1, $clonedPlacement1->getX());
        $this->assertEquals(4, $clonedPlacement1->getY());
    }

    public function testGetIndex(): void
    {
        $reflectionClass = new ReflectionClass(Group::class);
        $reflectionClass->setStaticPropertyValue('indexCounter', 0);

        $group1 = new Group();
        $group2 = new Group();
        $group3 = new Group();

        $this->assertEquals(0, $group1->getIndex());
        $this->assertEquals(1, $group2->getIndex());
        $this->assertEquals(2, $group3->getIndex());
    }

    public function testToString(): void
    {
        $reflectionClass = new ReflectionClass(Group::class);
        $reflectionClass->setStaticPropertyValue('indexCounter', 0);

        $group1 = new Group();
        $group2 = new Group();
        $group3 = new Group();

        $this->assertEquals('group #0', (string) $group1);
        $this->assertEquals('group #1', (string) $group2);
        $this->assertEquals('group #2', (string) $group3);
    }
}
