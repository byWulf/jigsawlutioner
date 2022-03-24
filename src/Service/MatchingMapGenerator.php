<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;

class MatchingMapGenerator
{
    public function __construct(
        private SideMatcherInterface $sideMatcher
    ) {
    }

    /**
     * @param Piece[] $pieces
     *
     * @return float[][]
     */
    public function getMatchingMap(array $pieces): array
    {
        $matchingMap = [];

        $allSides = [];
        foreach ($pieces as $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $allSides[$this->getKey($piece->getIndex(), $sideIndex)] = $side;
            }
        }

        foreach ($pieces as $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $probabilities = $this->sideMatcher->getMatchingProbabilities($side, $allSides);
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
