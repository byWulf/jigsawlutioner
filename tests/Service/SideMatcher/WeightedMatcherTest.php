<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use PHPStan\Testing\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class WeightedMatcherTest extends TestCase
{
    use ProphecyTrait;

    private WeightedMatcher $service;

    public function setUp(): void
    {
        $this->service = new WeightedMatcher();
    }

    public function testGetMatchingProbability(): void
    {
        $nopInformation = $this->getOrderedSides();

        for ($x = 0; $x < 25; ++$x) {
            for ($y = 0; $y < 20; ++$y) {
                $rightSide = $nopInformation[$y * 25 + $x + 2][3] ?? null;
                $rightOppositeSide = $x === 24 ? null : ($nopInformation[$y * 25 + $x + 3][1] ?? null);

                $bottomSide = $nopInformation[$y * 25 + $x + 2][2] ?? null;
                $bottomOppositeSide = $y === 19 ? null : ($nopInformation[($y + 1) * 25 + $x + 2][0] ?? null);

                $leftSide = $nopInformation[$y * 25 + $x + 2][1] ?? null;
                $leftOppositeSide = $x === 0 ? null : ($nopInformation[$y * 25 + $x + 1][3] ?? null);

                $topSide = $nopInformation[$y * 25 + $x + 2][0] ?? null;
                $topOppositeSide = $y === 0 ? null : ($nopInformation[($y - 1) * 25 + $x + 2][2] ?? null);

                $this->outputSideMatching($topSide, $topOppositeSide, $x . '/' . $y . ' (top)');
                $this->outputSideMatching($leftSide, $leftOppositeSide, $x . '/' . $y . ' (left)');
                $this->outputSideMatching($bottomSide, $bottomOppositeSide, $x . '/' . $y . ' (bottom)');
                $this->outputSideMatching($rightSide, $rightOppositeSide, $x . '/' . $y . ' (right)');
            }
        }
    }

    private function outputSideMatching(?Side $side1, ?Side $side2, string $label): void
    {
        if ($side1 === null || $side2 === null) {
            return;
        }

        echo $label . ': ' . round($this->service->getMatchingProbability($side1, $side2), 1) . PHP_EOL;
    }

    public function testGetMatchingProbabilities(): void
    {
        $nopInformation = $this->getOrderedSides();

        $allSides = array_merge(...$nopInformation);

        $countMatchings = 0;
        $matchingPositionsSum = 0;
        for ($x = 0; $x < 25; ++$x) {
            for ($y = 0; $y < 20; ++$y) {
                $rightSide = $nopInformation[$y * 25 + $x + 2][3] ?? null;
                $rightOppositeSide = $x === 24 ? null : ($nopInformation[$y * 25 + $x + 3][1] ?? null);

                $bottomSide = $nopInformation[$y * 25 + $x + 2][2] ?? null;
                $bottomOppositeSide = $y === 19 ? null : ($nopInformation[($y + 1) * 25 + $x + 2][0] ?? null);

                $leftSide = $nopInformation[$y * 25 + $x + 2][1] ?? null;
                $leftOppositeSide = $x === 0 ? null : ($nopInformation[$y * 25 + $x + 1][3] ?? null);

                $topSide = $nopInformation[$y * 25 + $x + 2][0] ?? null;
                $topOppositeSide = $y === 0 ? null : ($nopInformation[($y - 1) * 25 + $x + 2][2] ?? null);

                $this->outputSideMatchings($topSide, $topOppositeSide, $allSides, $x . '/' . $y . ' (top)', $countMatchings, $matchingPositionsSum);
                $this->outputSideMatchings($leftSide, $leftOppositeSide, $allSides, $x . '/' . $y . ' (left)', $countMatchings, $matchingPositionsSum);
                $this->outputSideMatchings($bottomSide, $bottomOppositeSide, $allSides, $x . '/' . $y . ' (bottom)', $countMatchings, $matchingPositionsSum);
                $this->outputSideMatchings($rightSide, $rightOppositeSide, $allSides, $x . '/' . $y . ' (right)', $countMatchings, $matchingPositionsSum);
            }
        }

        echo "Average position: " . ($matchingPositionsSum / $countMatchings) . PHP_EOL;
    }

    private function outputSideMatchings(?Side $side1, ?Side $side2, array $sides, string $label, &$countMatchings, &$matchingPositionsSum): void
    {
        if ($side1 === null || $side2 === null) {
            return;
        }

        $probabilities = $this->service->getMatchingProbabilities($side1, $sides);
        arsort($probabilities);
        $targetIndex = array_search($side2, $sides);
        $position = array_search($targetIndex, array_keys($probabilities));

        $countMatchings++;
        $matchingPositionsSum += $position;

        echo $label . ': #' . ($position + 1) . ' (' . implode(', ', array_map(fn (float $num): float => round($num, 2), array_slice($probabilities, 0, 10))) . ($position > 9 ? ', ..., ' . round($probabilities[$targetIndex], 2) : '') . ')' . PHP_EOL;
    }

    /**
     * @return Side[][]
     */
    private function getOrderedSides(): array
    {
        $nopInformation = [];
        for ($i = 2; $i <= 501; ++$i) {
            //echo "piece " . $i . PHP_EOL;
            $piece = unserialize(file_get_contents(__DIR__ . '/../../fixtures/pieces/piece' . $i . '_piece.ser'));

            if (count($piece->getSides()) !== 4) {
                continue;
            }

            // Reorder sides so the top side is the first side
            $sides = $piece->getSides();
            while (
                $sides[1]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY() ||
                $sides[2]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY() ||
                $sides[3]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY()
            ) {
                $side = array_splice($sides, 0, 1);
                $sides[] = $side[0];
                $sides = array_values($sides);
            }

            foreach (array_values($sides) as $index => $side) {
                $nopInformation[$i][$index] = $side;
            }
        }

        return $nopInformation;
    }
}
