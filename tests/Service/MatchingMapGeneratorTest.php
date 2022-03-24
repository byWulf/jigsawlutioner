<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\MatchingMapGenerator;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Bywulf\Jigsawlutioner\Service\MatchingMapGenerator
 */
class MatchingMapGeneratorTest extends TestCase
{
    use ProphecyTrait;

    public function testGetMatchingMap(): void
    {
        $piece1 = new Piece(1, [], [
            new Side([], new Point(1, 0), new Point(1, 0)),
            new Side([], new Point(1, 1), new Point(1, 1)),
            new Side([], new Point(1, 2), new Point(1, 2)),
            new Side([], new Point(1, 3), new Point(1, 3)),
        ], 1, 1);
        $piece2 = new Piece(2, [], [
            new Side([], new Point(2, 0), new Point(2, 0)),
            new Side([], new Point(2, 1), new Point(2, 1)),
            new Side([], new Point(2, 2), new Point(2, 2)),
            new Side([], new Point(2, 3), new Point(2, 3)),
        ], 1, 1);
        $piece3 = new Piece(3, [], [
            new Side([], new Point(3, 0), new Point(3, 0)),
            new Side([], new Point(3, 1), new Point(3, 1)),
            new Side([], new Point(3, 2), new Point(3, 2)),
            new Side([], new Point(3, 3), new Point(3, 3)),
        ], 1, 1);

        $sideMatcher = $this->prophesize(SideMatcherInterface::class);
        $matchingMapGenerator = new MatchingMapGenerator($sideMatcher->reveal());

        $sideMatcher->getMatchingProbabilities($piece1->getSide(0), Argument::any())->willReturn($this->getMockProbabilities(1, 0));
        $sideMatcher->getMatchingProbabilities($piece1->getSide(1), Argument::any())->willReturn($this->getMockProbabilities(1, 1));
        $sideMatcher->getMatchingProbabilities($piece1->getSide(2), Argument::any())->willReturn($this->getMockProbabilities(1, 2));
        $sideMatcher->getMatchingProbabilities($piece1->getSide(3), Argument::any())->willReturn($this->getMockProbabilities(1, 3));
        $sideMatcher->getMatchingProbabilities($piece2->getSide(0), Argument::any())->willReturn($this->getMockProbabilities(2, 0));
        $sideMatcher->getMatchingProbabilities($piece2->getSide(1), Argument::any())->willReturn($this->getMockProbabilities(2, 1));
        $sideMatcher->getMatchingProbabilities($piece2->getSide(2), Argument::any())->willReturn($this->getMockProbabilities(2, 2));
        $sideMatcher->getMatchingProbabilities($piece2->getSide(3), Argument::any())->willReturn($this->getMockProbabilities(2, 3));
        $sideMatcher->getMatchingProbabilities($piece3->getSide(0), Argument::any())->willReturn($this->getMockProbabilities(3, 0));
        $sideMatcher->getMatchingProbabilities($piece3->getSide(1), Argument::any())->willReturn($this->getMockProbabilities(3, 1));
        $sideMatcher->getMatchingProbabilities($piece3->getSide(2), Argument::any())->willReturn($this->getMockProbabilities(3, 2));
        $sideMatcher->getMatchingProbabilities($piece3->getSide(3), Argument::any())->willReturn($this->getMockProbabilities(3, 3));

        $matchingMap = $matchingMapGenerator->getMatchingMap([$piece1, $piece2, $piece3]);

        $this->assertEquals([
            '1_0' => $this->getExpectedProbabilities(1, 0),
            '1_1' => $this->getExpectedProbabilities(1, 1),
            '1_2' => $this->getExpectedProbabilities(1, 2),
            '1_3' => $this->getExpectedProbabilities(1, 3),
            '2_0' => $this->getExpectedProbabilities(2, 0),
            '2_1' => $this->getExpectedProbabilities(2, 1),
            '2_2' => $this->getExpectedProbabilities(2, 2),
            '2_3' => $this->getExpectedProbabilities(2, 3),
            '3_0' => $this->getExpectedProbabilities(3, 0),
            '3_1' => $this->getExpectedProbabilities(3, 1),
            '3_2' => $this->getExpectedProbabilities(3, 2),
            '3_3' => $this->getExpectedProbabilities(3, 3),
        ], $matchingMap);
    }

    private function getMockProbabilities(int $pieceIndex, int $sideIndex): array
    {
        $offset = $pieceIndex * 20 + $sideIndex;

        return [
            '1_0' => $offset + 0,
            '1_1' => $offset + 1,
            '1_2' => $offset + 2,
            '1_3' => $offset + 3,
            '2_0' => $offset + 4,
            '2_1' => $offset + 5,
            '2_2' => $offset + 6,
            '2_3' => $offset + 7,
            '3_0' => $offset + 8,
            '3_1' => $offset + 9,
            '3_2' => $offset + 10,
            '3_3' => $offset + 11,
        ];
    }

    private function getExpectedProbabilities(int $pieceIndex, int $sideIndex): array
    {
        $offset = $pieceIndex * 20 + $sideIndex;

        $probabilities = [
            '3_3' => $offset + 11,
            '3_2' => $offset + 10,
            '3_1' => $offset + 9,
            '3_0' => $offset + 8,
            '2_3' => $offset + 7,
            '2_2' => $offset + 6,
            '2_1' => $offset + 5,
            '2_0' => $offset + 4,
            '1_3' => $offset + 3,
            '1_2' => $offset + 2,
            '1_1' => $offset + 1,
            '1_0' => $offset + 0,
        ];

        unset($probabilities[$pieceIndex . '_0'], $probabilities[$pieceIndex . '_1'], $probabilities[$pieceIndex . '_2'], $probabilities[$pieceIndex . '_3']);

        return $probabilities;
    }
}
