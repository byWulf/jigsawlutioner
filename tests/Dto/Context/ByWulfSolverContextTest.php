<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto\Context;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Solution;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext
 */
class ByWulfSolverContextTest extends TestCase
{

    public function testGetters(): void
    {
        $piece1 = new Piece(1, [], [], 1, 1);
        $piece2 = new Piece(2, [], [], 2, 2);

        $stepProgression = fn () => 234;

        $context = new ByWulfSolverContext([$piece1, $piece2], [], $stepProgression);

        $this->assertEquals(new Solution(), $context->getSolution());
        $this->assertEquals([$piece1, $piece2], array_values($context->getPieces()));
        $this->assertEquals($piece1, $context->getPiece(1));
        $this->assertEquals(2, $context->getPiecesCount());
        $this->assertEquals($stepProgression, $context->getStepProgression());
    }

    public function testGettingInvalidPiece(): void
    {
        $piece1 = new Piece(1, [], [], 1, 1);
        $piece2 = new Piece(2, [], [], 2, 2);

        $context = new ByWulfSolverContext([$piece1, $piece2], []);

        $this->expectException(InvalidArgumentException::class);
        $context->getPiece(3);
    }

    public function testMatchingMap(): void
    {
        $piece1 = new Piece(1, [], [], 1, 1);
        $piece2 = new Piece(2, [], [], 2, 2);

        $initialMatchingMap = [
            '1_0' => ['2_0' => 0.1, '2_1' => 0.2, '2_2' => 0.3, '2_3' => 0.4],
            '1_1' => ['2_0' => 1.1, '2_1' => 1.2, '2_2' => 1.3, '2_3' => 1.4],
            '1_2' => ['2_0' => 2.1, '2_1' => 2.2, '2_2' => 2.3, '2_3' => 2.4],
            '1_3' => ['2_0' => 3.1, '2_1' => 3.2, '2_2' => 3.3, '2_3' => 3.4],
            '2_0' => ['1_0' => 10.1, '1_1' => 10.2, '1_2' => 10.3, '1_3' => 10.4],
            '2_1' => ['1_0' => 11.1, '1_1' => 11.2, '1_2' => 11.3, '1_3' => 11.4],
            '2_2' => ['1_0' => 12.1, '1_1' => 12.2, '1_2' => 12.3, '1_3' => 12.4],
            '2_3' => ['1_0' => 13.1, '1_1' => 13.2, '1_2' => 13.3, '1_3' => 13.4],
        ];

        $newMatchingMap = [
            '6_0' => ['5_1' => 2.3],
        ];

        $context = new ByWulfSolverContext([$piece1, $piece2], $initialMatchingMap);

        $this->assertEquals($initialMatchingMap, $context->getMatchingMap());
        $this->assertEquals($initialMatchingMap, $context->getOriginalMatchingMap());
        $this->assertEquals(['2_0' => 3.1, '2_1' => 3.2, '2_2' => 3.3, '2_3' => 3.4], $context->getMatchingProbabilities('1_3'));
        $this->assertEquals([], $context->getMatchingProbabilities('not existing'));
        $this->assertEquals(3.2, $context->getMatchingProbability('1_3', '2_1'));
        $this->assertEquals(0, $context->getMatchingProbability('1_3', 'not existing'));
        $this->assertEquals(0, $context->getMatchingProbability('not existing', '2_1'));
        $this->assertEquals(3.2, $context->getOriginalMatchingProbability('1_3', '2_1'));
        $this->assertEquals(0, $context->getOriginalMatchingProbability('1_3', 'not existing'));
        $this->assertEquals(0, $context->getOriginalMatchingProbability('not existing', '2_1'));

        $context->setMatchingMap($newMatchingMap);

        $this->assertEquals($newMatchingMap, $context->getMatchingMap());
        $this->assertEquals($initialMatchingMap, $context->getOriginalMatchingMap());
        $this->assertEquals(['5_1' => 2.3], $context->getMatchingProbabilities('6_0'));
        $this->assertEquals([], $context->getMatchingProbabilities('not existing'));
        $this->assertEquals(2.3, $context->getMatchingProbability('6_0', '5_1'));
        $this->assertEquals(0, $context->getMatchingProbability('6_0', 'not existing'));
        $this->assertEquals(0, $context->getMatchingProbability('not existing', '5_1'));
        $this->assertEquals(3.2, $context->getOriginalMatchingProbability('1_3', '2_1'));
        $this->assertEquals(0, $context->getOriginalMatchingProbability('1_3', 'not existing'));
        $this->assertEquals(0, $context->getOriginalMatchingProbability('not existing', '2_1'));
        $this->assertEquals(0, $context->getOriginalMatchingProbability('6_0', '5_1'));

        $context->setMatchingMap($initialMatchingMap);
        $context->unsetMatchingMapKey('1_3');

        $this->assertEquals(0, $context->getMatchingProbability('1_3', '2_1'));
        $this->assertEquals(2.2, $context->getMatchingProbability('1_2', '2_1'));
        $this->assertEquals(3.2, $context->getOriginalMatchingProbability('1_3', '2_1'));
    }

    public function testRemovingAllowed(): void
    {
        $context = new ByWulfSolverContext([], []);

        $this->assertTrue($context->isRemovingAllowed());

        $context->setRemovingAllowed(false);

        $this->assertFalse($context->isRemovingAllowed());

        $context->setRemovingAllowed(true);

        $this->assertTrue($context->isRemovingAllowed());
    }
}
