<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Bywulf\Jigsawlutioner\Tests\PieceLoaderTrait;
use PHPStan\Testing\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class WeightedMatcherTest extends TestCase
{
    use ProphecyTrait;
    use PieceLoaderTrait;

    private WeightedMatcher $service;

    public function setUp(): void
    {
        $this->service = new WeightedMatcher();
    }

    public function testGetMatchingProbabilities(): void
    {
        $pieces = $this->getPieces();

        $allSides = array_merge(...array_map(fn (Piece $piece): array => $piece->getSides(), $pieces));

        $countMatchings = 0;
        $matchingPositionsSum = 0;
        for ($x = 0; $x < 25; ++$x) {
            for ($y = 0; $y < 20; ++$y) {
                $rightSide = $this->getSide($pieces, $y * 25 + $x + 2, 3);
                $rightOppositeSide = $x === 24 ? null : ($this->getSide($pieces, $y * 25 + $x + 3, 1));

                $bottomSide = $this->getSide($pieces, $y * 25 + $x + 2, 2);
                $bottomOppositeSide = $y === 19 ? null : ($this->getSide($pieces, ($y + 1) * 25 + $x + 2, 0));

                $leftSide = $this->getSide($pieces, $y * 25 + $x + 2, 1);
                $leftOppositeSide = $x === 0 ? null : ($this->getSide($pieces, $y * 25 + $x + 1, 3));

                $topSide = $this->getSide($pieces, $y * 25 + $x + 2, 0);
                $topOppositeSide = $y === 0 ? null : ($this->getSide($pieces, ($y - 1) * 25 + $x + 2, 2));

                $this->outputSideMatchings($topSide, $topOppositeSide, $allSides, $x . '/' . $y . ' (top)', $countMatchings, $matchingPositionsSum);
                $this->outputSideMatchings($leftSide, $leftOppositeSide, $allSides, $x . '/' . $y . ' (left)', $countMatchings, $matchingPositionsSum);
                $this->outputSideMatchings($bottomSide, $bottomOppositeSide, $allSides, $x . '/' . $y . ' (bottom)', $countMatchings, $matchingPositionsSum);
                $this->outputSideMatchings($rightSide, $rightOppositeSide, $allSides, $x . '/' . $y . ' (right)', $countMatchings, $matchingPositionsSum);
            }
        }

        echo 'BigWidthClassifier: ' . BigWidthClassifier::getAverageTime() . PHP_EOL;
        echo 'SmallWidthClassifier: ' . SmallWidthClassifier::getAverageTime() . PHP_EOL;
        echo 'Current average position: ' . ($matchingPositionsSum / $countMatchings) . ' (last known average position: 2.778)' . PHP_EOL;

        $this->assertLessThanOrEqual(2.8, $matchingPositionsSum / $countMatchings);
    }

    /**
     * @param Piece[] $pieces
     */
    private function getSide(array $pieces, int $pieceIndex, int $sideIndex): ?Side
    {
        if (!isset($pieces[$pieceIndex])) {
            return null;
        }

        return $pieces[$pieceIndex]->getSides()[$sideIndex] ?? null;
    }

    /**
     * @param Side[] $sides
     */
    private function outputSideMatchings(?Side $side1, ?Side $side2, array $sides, string $label, int &$countMatchings, int &$matchingPositionsSum): void
    {
        if ($side1 === null || $side2 === null) {
            return;
        }

        $probabilities = $this->service->getMatchingProbabilities($side1, $sides);
        arsort($probabilities);
        $targetIndex = array_search($side2, $sides);
        $position = array_search($targetIndex, array_keys($probabilities));

        ++$countMatchings;
        $matchingPositionsSum += $position;

        echo $label . ': #' . ($position + 1) . ' (' . implode(', ', array_map(fn (float $num): float => round($num, 2), array_slice($probabilities, 0, 10))) . ($position > 9 ? ', ..., ' . round($probabilities[$targetIndex], 2) : '') . ')' . PHP_EOL;
    }
}
