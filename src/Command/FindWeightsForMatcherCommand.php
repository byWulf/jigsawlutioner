<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use Bywulf\Jigsawlutioner\Util\TimeTrackerTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:weight-matcher:find-weights')]
class FindWeightsForMatcherCommand extends Command
{
    use PieceLoaderTrait;
    use TimeTrackerTrait;

    private WeightedMatcher $weightedMatcher;

    public function __construct()
    {
        parent::__construct();

        $this->weightedMatcher = new WeightedMatcher();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pieces = $this->getPieces('newcam_test_ordered');
        $allSides = array_merge(...array_map(fn (Piece $piece): array => $piece->getSides(), $pieces));

        $performance = [];

        for ($bigWidth = 0.95893780825214; $bigWidth <= 0.95893780825214; $bigWidth += 0.5) {
            for ($cornerDistance = 0.84754176512042; $cornerDistance <= 0.84754176512042; $cornerDistance += 0.5) {
                for ($depth = 0.81363284548269; $depth <= 0.81363284548269; $depth += 0.5) {
                    for ($smallWidth = 0.94909504955181; $smallWidth <= 0.94909504955181; $smallWidth += 0.5) {
                        $this->weightedMatcher->setWeights([
                            BigWidthClassifier::class => $bigWidth,
                            CornerDistanceClassifier::class => $cornerDistance,
                            DepthClassifier::class => $depth,
                            SmallWidthClassifier::class => $smallWidth,
                        ]);

                        $key = $bigWidth . ' // ' . $cornerDistance . ' // ' . $depth . ' // ' . $smallWidth;

                        $performance[$key] = $this->withTimeTracking(function() use ($pieces, $allSides) {
                            return $this->testPerformance($pieces, $allSides);
                        });
                        $time = self::getAverageTime();
                        self::reset();

                        echo $bigWidth . "\t" . $cornerDistance . "\t" . $depth . "\t" . $smallWidth . "\t" . $performance[$key] . "\t" . '(' . $time . ')' . "\n";
                    }
                }
            }
        }

        arsort($performance);

        var_export($performance);

        return self::SUCCESS;
    }

    private function testPerformance(array $pieces, array $allSides): float
    {
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

                $this->addPosition($topSide, $topOppositeSide, $allSides, $countMatchings, $matchingPositionsSum);
                $this->addPosition($leftSide, $leftOppositeSide, $allSides, $countMatchings, $matchingPositionsSum);
                $this->addPosition($bottomSide, $bottomOppositeSide, $allSides, $countMatchings, $matchingPositionsSum);
                $this->addPosition($rightSide, $rightOppositeSide, $allSides, $countMatchings, $matchingPositionsSum);
            }
        }

        return $matchingPositionsSum / $countMatchings;
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
    private function addPosition(?Side $side1, ?Side $side2, array $sides, int &$countMatchings, int &$matchingPositionsSum): void
    {
        if ($side1 === null || $side2 === null) {
            return;
        }

        $targetIndex = array_search($side2, $sides);

        $probabilities = $this->weightedMatcher->getMatchingProbabilities($side1, $sides);
        arsort($probabilities);

        $probability = $probabilities[$targetIndex];
        $sidesWithProbability = count(array_keys($probabilities, $probability));
        $firstPosition = array_search($probability, array_values($probabilities));

        ++$countMatchings;
        $matchingPositionsSum += $firstPosition + $sidesWithProbability - 1;
    }
}
