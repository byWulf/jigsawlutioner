<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class ByWulfSolver implements PuzzleSolverInterface
{
    private const DIRECTION_OFFSETS = [
        0 => ['x' => 0, 'y' => -1],
        1 => ['x' => -1, 'y' => 0],
        2 => ['x' => 0, 'y' => 1],
        3 => ['x' => 1, 'y' => 0],
    ];

    private Solution $solution;

    private array $pieces;

    private array $matchingMap;

    public function __construct(
        private SideMatcherInterface $sideMatcher,
        private ?LoggerInterface $logger = null
    ) {
        $this->cache = new FilesystemAdapter(directory: __DIR__ . '/../../../resources/cache');
    }

    /**
     * @param Piece[] $pieces
     */
    public function findSolution(array $pieces): Solution
    {
        $this->solution = new Solution();
        $this->pieces = $pieces;
        $this->missingPieces = $pieces;

        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Creating matching probability map...');
        $this->matchingMap = $this->getMatchingMap($pieces);
        //$this->matchingMap = $this->cache->get('matchingMap1', fn () => $this->getMatchingMap($pieces));

        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Starting to find solution...');

        // Loop as long as placements can be made
        while ($this->addNextPlacement()) {
            $placements = array_sum(array_map(fn(Group $group): int => count($group->getPlacements()), $this->solution->getGroups()));
            $groups = count($this->solution->getGroups());

            $this->logger?->debug((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Placed ' . $placements . ' pieces in ' . $groups . ' groups.');
        }

        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Finished creating solution.');

        return $this->solution;
    }

    private function addNextPlacement(): bool
    {
        $nextKey = $this->getMostFittableSide($this->pieces, $this->matchingMap);
        // If nothing more can be placed, we add all non-assigned pieces as single groups and end the solution creation
        if ($nextKey === null) {
            foreach ($this->pieces as $piece) {
                foreach ($this->solution->getGroups() as $group) {
                    if ($group->getPlacementByPiece($piece) !== null) {
                        continue 2;
                    }
                }

                $group = new Group();
                $group->addPlacement(new Placement(0, 0, $piece, 0));
                $this->solution->addGroup($group);
            }

            return false;
        }
        $nextPieceIndex = $this->getPieceIndexFromKey($nextKey);

        $matchingKey = array_key_first($this->matchingMap[$nextKey]);
        $matchingPieceIndex = $this->getPieceIndexFromKey($matchingKey);

        $groups = $this->getGroups([$nextPieceIndex, $matchingPieceIndex]);
        if (count($groups) === 0) {
            $group = new Group();
            $this->solution->addGroup($group);
            $group->addPlacement(new Placement(0, 0, $this->pieces[$nextPieceIndex], 0));

            $this->addPlacementToGroup($group, $nextKey, $matchingKey);
        } elseif (count($groups) === 1) {
            $this->addPlacementToGroup(reset($groups), $nextKey, $matchingKey);
        } elseif (count($groups) === 2) {
            $this->mergeGroups($groups[$nextPieceIndex], $groups[$matchingPieceIndex], $nextKey, $matchingKey);
        } else {
            throw new PuzzleSolverException('Expected 0 to 2 groups, got ' . count($groups) . '.');
        }

        unset($this->matchingMap[$nextKey], $this->matchingMap[$matchingKey]);

        return true;
    }

    private function addPlacementToGroup(Group $group, string $key1, string $key2): void
    {
        $placement1 = $group->getPlacementByPiece($this->pieces[$this->getPieceIndexFromKey($key1)]);
        $placement2 = $group->getPlacementByPiece($this->pieces[$this->getPieceIndexFromKey($key2)]);

        if ($placement1 !== null && $placement2 === null) {
            $existingPlacement = $placement1;
            $existingSideIndex = $this->getSideIndexFromKey($key1);

            $newPiece = $this->pieces[$this->getPieceIndexFromKey($key2)];
            $newSideIndex = $this->getSideIndexFromKey($key2);
        } elseif ($placement2 !== null && $placement1 === null) {
            $existingPlacement = $placement2;
            $existingSideIndex = $this->getSideIndexFromKey($key2);

            $newPiece = $this->pieces[$this->getPieceIndexFromKey($key1)];
            $newSideIndex = $this->getSideIndexFromKey($key1);
        } else {
            throw new PuzzleSolverException('Trying to place a piece to an existing group, but something strange happened in the data.');
        }

        $group->addPlacement(new Placement(
            $existingPlacement->getX() + self::DIRECTION_OFFSETS[($existingSideIndex - $existingPlacement->getTopSideIndex() + 4) % 4]['x'],
            $existingPlacement->getY() + self::DIRECTION_OFFSETS[($existingSideIndex - $existingPlacement->getTopSideIndex() + 4) % 4]['y'],
            $newPiece,
            (($existingSideIndex + 2 - $existingPlacement->getTopSideIndex() + $newSideIndex) + 4) % 4
        ));
    }

    private function mergeGroups(Group $group1, Group $group2, string $key1, string $key2): void
    {
        // If its the same groups, we cannot merge them
        if ($group1 === $group2) {
            return;
        }

        $piece1 = $this->pieces[$this->getPieceIndexFromKey($key1)];
        $sideIndex1 = $this->getSideIndexFromKey($key1);
        $placement1 = $group1->getPlacementByPiece($piece1);

        if ($placement1 === null) {
            throw new PuzzleSolverException('Something went wrong.');
        }

        $piece2 = $this->pieces[$this->getPieceIndexFromKey($key2)];
        $sideIndex2 = $this->getSideIndexFromKey($key2);

        // First clone the second group to manipulate the clone for testing the fittment
        $group2Copy = clone $group2;
        $placement2 = $group2Copy->getPlacementByPiece($piece2);

        if ($placement2 === null) {
            throw new PuzzleSolverException('Something went wrong.');
        }

        $neededRotations = $placement1->getTopSideIndex() - $sideIndex1 - $placement2->getTopSideIndex() + $sideIndex2 + 2;
        $group2Copy->rotate($neededRotations);

        $targetX = $placement1->getX() + self::DIRECTION_OFFSETS[($sideIndex1 - $placement1->getTopSideIndex() + 4) % 4]['x'];
        $targetY = $placement1->getY() + self::DIRECTION_OFFSETS[($sideIndex1 - $placement1->getTopSideIndex() + 4) % 4]['y'];
        $group2Copy->move($targetX - $placement2->getX(), $targetY - $placement2->getY());

        //Check that no position is occupied multiple times
        foreach ($group1->getPlacements() as $placement) {
            if ($group2Copy->getPlacementByPosition($placement->getX(), $placement->getY())) {
                return;
            }
        }
        //TODO: Chekc that all connecting sides are matching by probability > 0.5
        //TODO: Check that is becomes a rectangle and not a tetris block

        foreach ($group2Copy->getPlacements() as $placement) {
            $group1->addPlacement($placement);
        }
        $this->solution->removeGroup($group2);
    }

    /**
     * @param Piece[]   $pieces
     * @param float[][] $matchingMap
     */
    private function getMostFittableSide(array $pieces, array $matchingMap): ?string
    {
        if (count($pieces) === 0) {
            return null;
        }

        $bestRating = 0;
        $bestKey = null;
        foreach ($matchingMap as $key => $probabilities) {
            list($bestProbability, $secondProbability) = array_slice(array_values($matchingMap[$key]), 0, 2);
            if ($bestProbability > 0.5 && $bestProbability - $secondProbability > $bestRating) {
                $bestRating = $bestProbability - $secondProbability;
                $bestKey = $key;
            }
        }

        if ($bestKey === null) {
            return null;
        }

        return $bestKey;
    }

    /**
     * @param Piece[] $pieces
     *
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

    private function getPieceIndexFromKey(string $key): int
    {
        return (int) explode('_', $key)[0];
    }

    private function getSideIndexFromKey(string $key): int
    {
        return (int) explode('_', $key)[1];
    }

    /**
     * @param int[] $pieceIndexes
     *
     * @return array<int, Group> Keys are the requested piece indexes
     */
    private function getGroups(array $pieceIndexes): array
    {
        $foundGroups = [];
        foreach ($this->solution->getGroups() as $groupIndex => $group) {
            foreach ($group->getPlacements() as $placement) {
                foreach ($pieceIndexes as $pieceIndex) {
                    if ($placement->getPiece() === $this->pieces[$pieceIndex]) {
                        $foundGroups[$pieceIndex] = $group;
                    }
                }
            }
        }

        return $foundGroups;
    }
}
