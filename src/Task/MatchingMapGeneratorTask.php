<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;

class MatchingMapGeneratorTask implements Task
{
    /**
     * @param array<int, Piece>   $pieces
     * @param array<string, Side> $allSides
     */
    public function __construct(
        private readonly SideMatcherInterface $sideMatcher,
        private readonly array $pieces,
        private readonly array $allSides,
    ) {
    }

    public function run(Environment $environment)
    {
        $matchingMap = [];
        foreach ($this->pieces as $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $probabilities = array_filter($this->sideMatcher->getMatchingProbabilities($side, $this->allSides));
                arsort($probabilities);
                $matchingMap[$this->getKey($piece->getIndex(), $sideIndex)] = $probabilities;

                // Remove own sides from map, because the puzzle must not be matched with itself
                for ($i = 0; $i < 4; ++$i) {
                    unset($matchingMap[$this->getKey($piece->getIndex(), $sideIndex)][$this->getKey($piece->getIndex(), $i)]);
                }
            }
        }

        return $matchingMap;
    }

    private function getKey(int $pieceNumber, int $sideIndex): string
    {
        return $pieceNumber . '_' . (($sideIndex + 4) % 4);
    }
}
