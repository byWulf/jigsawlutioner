<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;

class ByWulfSolver implements PuzzleSolverInterface
{
    private const DIRECTION_OFFSETS = [
        0 => ['x' => 0, 'y' => -1],
        1 => ['x' => -1, 'y' => 0],
        2 => ['x' => 0, 'y' => 1],
        3 => ['x' => 1, 'y' => 0],
    ];

    public function __construct(
        private SideMatcherInterface $sideMatcher
    ) {
    }

    /**
     * @param Piece[] $pieces
     */
    public function findSolution(array $pieces): Solution
    {
        $groups = [];
        $currentGroup = new Group();
        $debugGroup = [];

        $matchingMap = $this->getMatchingMap($pieces);

        foreach ($matchingMap as $pieceSide => $probabilities) {
            echo $pieceSide . ': ' . reset($probabilities) . PHP_EOL;
        }

        $nextPieceIndex = $this->getMostFittablePieceIndex($pieces, $matchingMap);
        if ($nextPieceIndex === null) {
            return new Solution($groups);
        }

        $currentX = 0;
        $currentY = 0;
        $currentDirection = 0;
        $currentGroup->addPlacement(new Placement($currentX, $currentY, $pieces[$nextPieceIndex], $currentDirection));
        $debugGroup[] = ['x' => $currentX, 'y' => $currentY, 'topDirection' => $currentDirection, 'pieceIndex' => $nextPieceIndex];

        $bestSideIndex = 0;
        $bestRating = 0;
        $matchingPieceIndex = null;
        $matchingSideIndex = null;
        foreach ($pieces[$nextPieceIndex]->getSides() as $sideIndex => $side) {
            $firstKey = array_key_first($matchingMap[$this->getKey($nextPieceIndex, $sideIndex)]);
            if ($matchingMap[$this->getKey($nextPieceIndex, $sideIndex)][$firstKey] > $bestRating) {
                $bestRating = $matchingMap[$this->getKey($nextPieceIndex, $sideIndex)][$firstKey];
                $bestSideIndex = $sideIndex;
                list($matchingPieceIndex, $matchingSideIndex) = $this->getIndexes($firstKey);
            }
        }

        $currentX += self::DIRECTION_OFFSETS[$bestSideIndex]['x'];
        $currentY += self::DIRECTION_OFFSETS[$bestSideIndex]['y'];
        $currentDirection = (($bestSideIndex + 2 - $currentDirection + $matchingSideIndex) + 4) % 4;
        $currentGroup->addPlacement(new Placement($currentX, $currentY, $pieces[$matchingPieceIndex], $currentDirection));
        $debugGroup[] = ['x' => $currentX, 'y' => $currentY, 'topDirection' => $currentDirection, 'pieceIndex' => $matchingPieceIndex];

        // TODO: From all free sides of the group, get the one with the best fitting. If multiple sides are neighbours to this piece, all sides must be the best matches

        var_export($debugGroup);
    }

    /**
     * @param Piece[] $pieces
     * @param float[][] $matchingMap
     */
    private function getMostFittablePieceIndex(array $pieces, array $matchingMap): int|string|null
    {
        if (count($pieces) === 0) {
            return null;
        }

        $bestRating = 0;
        $bestPieceIndex = null;
        foreach ($pieces as $pieceIndex => $piece) {
            $rating = 0;
            foreach ($piece->getSides() as $sideIndex => $side) {
                $rating += reset($matchingMap[$this->getKey($pieceIndex, $sideIndex)]);
            }

            if ($rating > $bestRating) {
                $bestRating = $rating;
                $bestPieceIndex = $pieceIndex;
            }
        }

        return $bestPieceIndex;
    }

    /**
     * @param Piece[] $pieces
     * @return float[][]
     */
    private function getMatchingMap(array $pieces): array
    {
        $matchingMap = [];

        $allSides = [];
        foreach ($pieces as $pieceIndex => $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $allSides[$this->getKey($pieceIndex, $sideIndex)] = $side;
            }
        }

        foreach ($pieces as $pieceIndex => $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $probabilities = $this->sideMatcher->getMatchingProbabilities($side, $allSides);
                arsort($probabilities);
                $matchingMap[$this->getKey($pieceIndex, $sideIndex)] = $probabilities;
            }
        }

        return $matchingMap;
    }

    private function getKey(int|string $pieceIndex, int $sideIndex): string
    {
        return $pieceIndex . '_' . $sideIndex;
    }

    /**
     * @return array<string|int>
     */
    private function getIndexes(string $key): array
    {
        $parts = explode('_', $key);
        return [$parts[0], (int) $parts[1]];
    }
}
