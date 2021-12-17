<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\Tests\PieceLoaderTrait;
use PHPStan\Testing\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

// TODO: Fix unittests
class WeightedMatcherTest extends TestCase
{
    use ProphecyTrait;
    use PieceLoaderTrait;

    private WeightedMatcher $service;

    public function setUp(): void
    {
        $this->service = new WeightedMatcher();
    }

    public function testGetMatchingProbability(): void
    {
        $pieces = $this->getPieces();

        for ($x = 0; $x < 25; ++$x) {
            for ($y = 0; $y < 20; ++$y) {
                $rightSide = $pieces[$y * 25 + $x + 2]->getSides()[3] ?? null;
                $rightOppositeSide = $x === 24 ? null : ($pieces[$y * 25 + $x + 3]->getSides()[1] ?? null);

                $bottomSide = $pieces[$y * 25 + $x + 2]->getSides()[2] ?? null;
                $bottomOppositeSide = $y === 19 ? null : ($pieces[($y + 1) * 25 + $x + 2]->getSides()[0] ?? null);

                $leftSide = $pieces[$y * 25 + $x + 2]->getSides()[1] ?? null;
                $leftOppositeSide = $x === 0 ? null : ($pieces[$y * 25 + $x + 1]->getSides()[3] ?? null);

                $topSide = $pieces[$y * 25 + $x + 2]->getSides()[0] ?? null;
                $topOppositeSide = $y === 0 ? null : ($pieces[($y - 1) * 25 + $x + 2]->getSides()[2] ?? null);

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

        echo $label . ': ' . round($this->service->getMatchingProbabilities($side1, [$side2])[0], 1) . PHP_EOL;
    }

    public function testGetMatchingProbabilities(): void
    {
        $pieces = $this->getPieces();

        $allSides = array_merge(...array_map(fn (Piece $piece): array => $piece->getSides(), $pieces));

        $countMatchings = 0;
        $matchingPositionsSum = 0;
        for ($x = 0; $x < 25; ++$x) {
            for ($y = 0; $y < 20; ++$y) {
                $rightSide = $pieces[$y * 25 + $x + 2]->getSides()[3] ?? null;
                $rightOppositeSide = $x === 24 ? null : ($pieces[$y * 25 + $x + 3]->getSides()[1] ?? null);

                $bottomSide = $pieces[$y * 25 + $x + 2]->getSides()[2] ?? null;
                $bottomOppositeSide = $y === 19 ? null : ($pieces[($y + 1) * 25 + $x + 2]->getSides()[0] ?? null);

                $leftSide = $pieces[$y * 25 + $x + 2]->getSides()[1] ?? null;
                $leftOppositeSide = $x === 0 ? null : ($pieces[$y * 25 + $x + 1]->getSides()[3] ?? null);

                $topSide = $pieces[$y * 25 + $x + 2]->getSides()[0] ?? null;
                $topOppositeSide = $y === 0 ? null : ($pieces[($y - 1) * 25 + $x + 2]->getSides()[2] ?? null);

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
}
