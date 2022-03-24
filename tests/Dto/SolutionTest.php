<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Solution;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Solution
 */
class SolutionTest extends TestCase
{
    public function testGetGroups(): void
    {
        $group1 = new Group();
        $group1->addPlacement(new Placement(1, 1, new Piece(1, [], [], 1, 1), 0));
        $solution = new Solution([$group1]);

        $this->assertEquals([$group1], array_values($solution->getGroups()));

        $group2 = new Group();
        $group2->addPlacement(new Placement(2, 2, new Piece(2, [], [], 2, 2), 0));
        $group3 = new Group();
        $group3->addPlacement(new Placement(3, 3, new Piece(3, [], [], 3, 3), 0));
        $solution->setGroups([$group2, $group3]);

        $this->assertEquals([$group2, $group3], array_values($solution->getGroups()));

        $solution->removeGroup($group2);

        $this->assertEquals([$group3], array_values($solution->getGroups()));

        $solution->addGroup($group1);

        $this->assertEquals([$group3, $group1], array_values($solution->getGroups()));
    }

    public function testGetBiggestGroup(): void
    {
        $group1 = new Group();
        $group1->addPlacement(new Placement(1, 1, new Piece(1, [], [], 1, 1), 0));

        $group2 = new Group();
        $group2->addPlacement(new Placement(2, 2, new Piece(2, [], [], 2, 2), 0));
        $group2->addPlacement(new Placement(5, 5, new Piece(5, [], [], 5, 5), 0));
        $group2->addPlacement(new Placement(6, 6, new Piece(6, [], [], 6, 6), 0));

        $group3 = new Group();
        $group3->addPlacement(new Placement(3, 3, new Piece(3, [], [], 3, 3), 0));
        $group3->addPlacement(new Placement(4, 4, new Piece(4, [], [], 4, 4), 0));

        $solution = new Solution([$group1, $group2, $group3]);

        $this->assertEquals($group2, $solution->getBiggestGroup());
    }

    public function testGetPieceCount(): void
    {
        $group1 = new Group();
        $group1->addPlacement(new Placement(1, 1, new Piece(1, [], [], 1, 1), 0));

        $group2 = new Group();
        $group2->addPlacement(new Placement(2, 2, new Piece(2, [], [], 2, 2), 0));
        $group2->addPlacement(new Placement(5, 5, new Piece(5, [], [], 5, 5), 0));
        $group2->addPlacement(new Placement(6, 6, new Piece(6, [], [], 6, 6), 0));

        $group3 = new Group();
        $group3->addPlacement(new Placement(3, 3, new Piece(3, [], [], 3, 3), 0));
        $group3->addPlacement(new Placement(4, 4, new Piece(4, [], [], 4, 4), 0));

        $solution = new Solution([$group1, $group2, $group3]);

        $this->assertEquals(6, $solution->getPieceCount());
    }

    public function testGetGroupByPiece(): void
    {
        $group1 = new Group();
        $group1->addPlacement(new Placement(1, 1, new Piece(1, [], [], 1, 1), 0));

        $group2 = new Group();
        $group2->addPlacement(new Placement(2, 2, new Piece(2, [], [], 2, 2), 0));
        $group2->addPlacement(new Placement(5, 5, new Piece(5, [], [], 5, 5), 0));
        $group2->addPlacement(new Placement(6, 6, new Piece(6, [], [], 6, 6), 0));

        $group3 = new Group();
        $piece1 = new Piece(3, [], [], 3, 3);
        $group3->addPlacement(new Placement(3, 3, $piece1, 0));
        $group3->addPlacement(new Placement(4, 4, new Piece(4, [], [], 4, 4), 0));

        $solution = new Solution([$group1, $group2, $group3]);

        $piece2 = new Piece(111, [], [], 111, 111);

        $this->assertEquals($group3, $solution->getGroupByPiece($piece1));
        $this->assertEquals(null, $solution->getGroupByPiece($piece2));
    }
}
