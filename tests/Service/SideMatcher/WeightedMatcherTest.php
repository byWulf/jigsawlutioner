<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use PHPStan\Testing\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class WeightedMatcherTest extends TestCase
{
    use ProphecyTrait;
    use PieceLoaderTrait;

    private SideMatcherInterface $service;

    public function setUp(): void
    {
        $this->service = new WeightedMatcher();
    }

    public function testGetMatchingProbabilities(): void
    {
        $pieces = $this->getPieces('newcam_test_ordered');

        $allSides = array_merge(...array_map(fn (Piece $piece): array => $piece->getSides(), $pieces));

        $countMatchings = 0;
        $matchingPositionsSum = 0;
        $onPos1 = 0;
        $distanceToPos2 = 0;
        $lengths = [];
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

                $this->outputSideMatchings($topSide, $topOppositeSide, $allSides, $x . '/' . $y . ' (top)', $countMatchings, $matchingPositionsSum, $onPos1, $distanceToPos2, $lengths);
                $this->outputSideMatchings($leftSide, $leftOppositeSide, $allSides, $x . '/' . $y . ' (left)', $countMatchings, $matchingPositionsSum, $onPos1, $distanceToPos2, $lengths);
                $this->outputSideMatchings($bottomSide, $bottomOppositeSide, $allSides, $x . '/' . $y . ' (bottom)', $countMatchings, $matchingPositionsSum, $onPos1, $distanceToPos2, $lengths);
                $this->outputSideMatchings($rightSide, $rightOppositeSide, $allSides, $x . '/' . $y . ' (right)', $countMatchings, $matchingPositionsSum, $onPos1, $distanceToPos2, $lengths);
            }
        }

        foreach (SideMatcherInterface::CLASSIFIER_CLASS_NAMES as $className) {
            echo $className . ': ' . $className::getAverageTime() . PHP_EOL;
        }

        echo 'On pos 1: ' . ($onPos1 / $countMatchings) . ' (last known 0.71940928270042) // avg distance to pos 2: ' . ($distanceToPos2 / $onPos1) . ' (last known 0.058021596172889)' . PHP_EOL;

        echo 'Current average position: ' . ($matchingPositionsSum / $countMatchings) . ' (last known average position: 0.97679324894515)' . PHP_EOL;

        sort($lengths);
        //echo 'Avg length diff: ' . implode(' // ', $lengths) . PHP_EOL;

        $this->assertLessThanOrEqual(0.97679324894515, $matchingPositionsSum / $countMatchings);
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
    private function outputSideMatchings(?Side $side1, ?Side $side2, array $sides, string $label, int &$countMatchings, int &$matchingPositionsSum, int &$onPos1, float &$distanceToPos2, array &$length): void
    {
        if ($side1 === null || $side2 === null) {
            return;
        }

        $targetIndex = array_search($side2, $sides);

        $probabilities = $this->service->getMatchingProbabilities($side1, $sides);
        arsort($probabilities);

        $probability = $probabilities[$targetIndex];
        $sidesWithProbability = count(array_keys($probabilities, $probability));
        $firstPosition = array_search($probability, array_values($probabilities));
        $position = $firstPosition + $sidesWithProbability - 1;

        $numProbabilities = array_values($probabilities);

        if ($position === 0) {
            $onPos1++;
            $distanceToPos2 += $numProbabilities[1] - $numProbabilities[2];
        }

        ++$countMatchings;
        $matchingPositionsSum += $position;

        //$length += abs($side1->getClassifier(CornerDistanceClassifier::class)->getWidth() - $side2->getClassifier(CornerDistanceClassifier::class)->getWidth());
        //$length += $side1->getClassifier(CornerDistanceClassifier::class)->getWidth();
        $length[] = abs($side1->getClassifier(CornerDistanceClassifier::class)->getWidth() - $side2->getClassifier(CornerDistanceClassifier::class)->getWidth());

        echo $label . ': #' . ($position + 1) . ' (' . implode(', ', array_map(fn (float $num): float => round($num, 2), array_slice($probabilities, 0, 10))) . ($position > 9 ? ', ..., ' . round($probabilities[$targetIndex], 2) : '') . ')' . PHP_EOL;
    }
}
