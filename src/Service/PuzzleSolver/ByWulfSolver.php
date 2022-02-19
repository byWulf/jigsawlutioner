<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\Validator\Group\PossibleSideMatching;
use Bywulf\Jigsawlutioner\Validator\Group\RealisticSide;
use Bywulf\Jigsawlutioner\Validator\Group\RectangleGroup;
use Bywulf\Jigsawlutioner\Validator\Group\UniquePlacement;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ByWulfSolver implements PuzzleSolverInterface
{
    public const DIRECTION_OFFSETS = [
        0 => ['x' => 0, 'y' => -1],
        1 => ['x' => -1, 'y' => 0],
        2 => ['x' => 0, 'y' => 1],
        3 => ['x' => 1, 'y' => 0],
    ];

    private Solution $solution;

    private array $pieces;

    private int $piecesCount;

    private array $matchingMap;

    private array $originalMatchingMap;

    private FilesystemAdapter $cache;

    private ValidatorInterface $validator;

    private bool $debug = false;

    public function __construct(
        private SideMatcherInterface $sideMatcher,
        private ?LoggerInterface $logger = null
    ) {
        $this->cache = new FilesystemAdapter(directory: __DIR__ . '/../../../resources/cache');
        $this->validator = Validation::createValidator();
    }

    /**
     * @param Piece[] $pieces
     */
    public function findSolution(array $pieces, string $cacheName = null, bool $useCache = true): Solution
    {
        $this->solution = new Solution();
        $this->pieces = $pieces;
        $this->piecesCount = count($pieces);
        $this->missingPieces = $pieces;

        if ($cacheName === null) {
            throw new InvalidArgumentException('$cacheName has to be given.');
        }

        if (!$useCache) {
            $this->cache->delete('matchingMap_' . $cacheName);
            $this->cache->commit();
        }

        $this->originalMatchingMap = $this->cache->get(sha1(__CLASS__ . '::matchingMap_' . $cacheName), function() {
            $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Creating matching probability map...');;
            return $this->getMatchingMap();
        });

        //foreach ([[0.6, 0.6], [0.7, 0.5], [0.8, 0.4], [0.9, 0.3], [0.5, 0.2], [0.5, 0.1], [0.01, 0.01]] as $minProbability) {
        foreach ([[0.8, 0.5], [0.6, 0.25], [0.5, 0.1], [0.01, 0.01]] as $minProbability) {
            $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Starting to find solution with minProbability of ' . implode('/', $minProbability) . '...');

            $this->matchingMap = $this->originalMatchingMap;

            // Loop as long as new pieces can be added
            while ($this->addNextPlacement([$this, 'getMostFittableSide'], $minProbability[0], $minProbability[1])) {
                $this->logger?->debug((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Placed ' . $this->solution->getPieceCount() . ' pieces in ' . count($this->solution->getGroups()) . ' groups.');
                //(new SolutionOutputter())->outputAsText($this->solution);
            }

            // Aftet that, look which groups can be merged the best
            while ($this->addNextPlacement([$this, 'getMostFittableGroupSide'], $minProbability[0], $minProbability[1])) {
                $this->logger?->debug((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Reduced groups into ' . count($this->solution->getGroups()) . ' groups.');
                //(new SolutionOutputter())->outputAsText($this->solution);
            }
        }

//        $this->matchingMap = $originalMatchingMap;
//        file_put_contents('C:\Users\michael.wolf\AppData\Roaming\JetBrains\PhpStorm2021.3\scratches\scratch_6.txt', '');
//        foreach ($this->matchingMap as $key => $map) {
//            file_put_contents(
//                'C:\Users\michael.wolf\AppData\Roaming\JetBrains\PhpStorm2021.3\scratches\scratch_6.txt',
//                '#' . $key . ': ' . implode(', ', array_map(fn(string $key, float $prob) => $key . '->' . round($prob,2), array_keys($map), $map)) . PHP_EOL,
//                FILE_APPEND
//            );
//        }
//        while ($this->addNextPlacement([$this, 'getMostFittableSide'], 0.75, 0.2)) {
//            $this->logger?->debug((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Placed ' . $this->solution->getPieceCount() . ' pieces in ' . count($this->solution->getGroups()) . ' groups.');
//            //(new SolutionOutputter())->outputAsText($this->solution);
//        }

        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Finished creating solution.');

        foreach ($this->pieces as $piece) {
            if ($this->solution->getGroupByPiece($piece)) {
                continue;
            }

            $group = new Group();
            $group->addPlacement(new Placement(0, 0, $piece, 0));
            $this->solution->addGroup($group);
        }

        $groups = $this->solution->getGroups();
        usort($groups, fn(Group $a, Group $b): int => count($b->getPlacements()) <=> count($a->getPlacements()));
        $this->solution->setGroups($groups);

        foreach ($this->solution->getGroups() as $group) {
            foreach ($group->getPlacements() as $placement) {
                $context = [];
                foreach (self::DIRECTION_OFFSETS as $indexOffset => $positionOffset) {
                    $sideKey = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $indexOffset);

                    $matchedPlacement = $group->getFirstPlacementByPosition($placement->getX() + $positionOffset['x'], $placement->getY() + $positionOffset['y']);
                    $matchedSideKey = null;
                    if ($matchedPlacement !== null) {
                        $matchedSideKey = $this->getKey($matchedPlacement->getPiece()->getIndex(), $matchedPlacement->getTopSideIndex() + 6 + $indexOffset);
                    }


                    $context[$indexOffset] = [
                        'probabilities' => array_values($this->originalMatchingMap[$sideKey] ?? []),
                        'matchedProbabilityIndex' => $matchedSideKey !== null ? array_search($matchedSideKey, array_keys($this->originalMatchingMap[$sideKey] ?? [])) : null,
                    ];
                }
                $placement->setContext($context);
            }
        }

        return $this->solution;
    }

    private function addNextPlacement(callable $nextKeyGetter, float $minProbability, float $minDifference): bool
    {
        $context = null;
        $nextKey = $nextKeyGetter($minProbability, $minDifference, $context);
        if ($nextKey === null) {
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
            $this->mergeGroups($groups[$nextPieceIndex], $groups[$matchingPieceIndex], $nextKey, $matchingKey, $minProbability, $context);
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

        $placement = new Placement(
            $existingPlacement->getX() + self::DIRECTION_OFFSETS[($existingSideIndex - $existingPlacement->getTopSideIndex() + 4) % 4]['x'],
            $existingPlacement->getY() + self::DIRECTION_OFFSETS[($existingSideIndex - $existingPlacement->getTopSideIndex() + 4) % 4]['y'],
            $newPiece,
            (($newSideIndex - $existingSideIndex + $existingPlacement->getTopSideIndex() + 2) + 4) % 4
        );

        $group->addPlacement($placement);

        if ($this->isGroupValid($group, 0)) {
            return;
        }

        // Because group is not valid now, we reset it and do nothing
        $group->removePlacement($placement);
    }

    private function mergeGroups(Group $group1, Group $group2, string $key1, string $key2, float $minProbability, mixed $context): void
    {
        if ($context['placementToRemove'] ?? null) {
            $group1->removePlacement($context['placementToRemove']);
        }

        $group2Copy = $this->getRepositionedGroup($group1, $group2, $key1, $key2, $minProbability);
        if (!$group2Copy) {
            if ($context['placementToRemove'] ?? null) {
                $group1->addPlacement($context['placementToRemove']);
            }

            return;
        }

        foreach ($group2Copy->getPlacements() as $placement) {
            $existingPlacement = $group1->getFirstPlacementByPosition($placement->getX(), $placement->getY());
            if ($existingPlacement) {
                $group1->removePlacement($existingPlacement);
            }

            $group1->addPlacement($placement);
        }

        $this->solution->removeGroup($group2);
    }

    private function getRepositionedGroup(Group $group1, Group $group2, string $key1, string $key2, float $minProbability, array &$probabilities = []): ?Group
    {
        // If its the same groups, we cannot merge them
        if ($group1 === $group2) {
            return null;
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

        //Check that the connecting sides have a matching probability of > 0.5
        $unmatchingSides = 0;
        foreach ($group2Copy->getPlacements() as $placement) {
            for ($sideOffset = 0; $sideOffset < 4; $sideOffset++) {
                $ownNeighbour = $group2Copy->getFirstPlacementByPosition(
                    $placement->getX() + self::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + self::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if ($ownNeighbour) {
                    continue;
                }

                $connectingPlacement = $group1->getFirstPlacementByPosition(
                    $placement->getX() + self::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + self::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if (!$connectingPlacement) {
                    continue;
                }

                $checkKey1 = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $sideOffset);
                $checkKey2 = $this->getKey($connectingPlacement->getPiece()->getIndex(), $connectingPlacement->getTopSideIndex() + $sideOffset + 2);

                if (($this->matchingMap[$checkKey1][$checkKey2] ?? 0) < $minProbability * 0.5) {
                    $unmatchingSides++;
                }

                $probabilities[] = $this->matchingMap[$checkKey1][$checkKey2] ?? 0;
            }
        }
        if ($unmatchingSides > count($probabilities) * 0.25) {
            return null;
        }

        if (count($probabilities) < min(count($group1->getPlacements()), count($group2->getPlacements())) * 0.1) {
            return null;
        }

        if (in_array(0.0, $probabilities, true)) {
            return null;
        }

        foreach ($group2Copy->getPlacements() as $placement) {
            $group1->addPlacement($placement);
        }

        $isGroupValid = $this->isGroupValid($group1, (int) round(count($group2Copy->getPlacements()) * 0.5));

        foreach ($group2Copy->getPlacements() as $placement) {
            $group1->removePlacement($placement);
        }

        return $isGroupValid ? $group2Copy : null;
    }

    /**
     * @param Piece[]   $pieces
     */
    private function getMostFittableSide(float $minProbability, float $minDifference): ?string
    {
        if (count($this->pieces) === 0) {
            return null;
        }

        $bestRating = 0;
        $bestKey = null;
        foreach ($this->matchingMap as $key => $probabilities) {
            if ($this->solution->getGroupByPiece($this->pieces[$this->getPieceIndexFromKey($key)])) {
                continue;
            }

            list($bestProbability, $secondProbability) = array_slice(array_values($this->matchingMap[$key]), 0, 2);
            $rating = ($bestProbability - $secondProbability) * ($bestProbability ** 3);
            if ($bestProbability >= $minProbability && ($bestProbability - $secondProbability) >= $minDifference && $rating > $bestRating) {
                $bestRating = $rating;
                $bestKey = $key;
            }
        }

        return $bestKey;
    }

    private function getMostFittableGroupSide(float $minProbability, float $minDifference, mixed &$context): ?string
    {
        $bestRating = 0;
        $bestKey = null;
        $placementToRemove = null;
        foreach (array_keys($this->matchingMap) as $key) {
            $group1 = $this->solution->getGroupByPiece($this->pieces[$this->getPieceIndexFromKey($key)]);
            if (!$group1) {
                continue;
            }

            $matchingKey = array_key_first($this->matchingMap[$key]);
            $group2 = $this->solution->getGroupByPiece($this->pieces[$this->getPieceIndexFromKey($matchingKey)]);
            if (!$group2) {
                continue;
            }

            if ($this->matchingMap[$key][$matchingKey] < $minProbability) {
                continue;
            }

            $probabilities = [];
            $group2Copy = $this->getRepositionedGroup($group1, $group2, $key, $matchingKey, $minProbability, $probabilities);
            if ($group2Copy !== null && min($probabilities) >= $minProbability && array_sum($probabilities) > $bestRating) {
                $bestRating = array_sum($probabilities);
                $bestKey = $key;
                $placementToRemove = null;
            }
        }

        $context = [
            'placementToRemove' => $placementToRemove,
        ];

        return $bestKey;
    }

    /**
     * @param Piece[] $pieces
     *
     * @return float[][]
     */
    private function getMatchingMap(): array
    {
        $matchingMap = [];

        $allSides = [];
        foreach ($this->pieces as $pieceIndex => $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $allSides[$this->getKey($pieceIndex, $sideIndex)] = $side;
            }
        }

        foreach ($this->pieces as $pieceIndex => $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $probabilities = $this->sideMatcher->getMatchingProbabilities($side, $allSides);
                arsort($probabilities);
                $matchingMap[$this->getKey($pieceIndex, $sideIndex)] = $probabilities;

                // Remove own sides from map, because the puzzle must not be matched with itself
                for ($i = 0; $i < 4; $i++) {
                    unset($matchingMap[$this->getKey($pieceIndex, $sideIndex)][$this->getKey($pieceIndex, $i)]);
                }
            }
        }

        return $matchingMap;
    }

    private function getKey(int|string $pieceIndex, int $sideIndex): string
    {
        return $pieceIndex . '_' . (($sideIndex + 4) % 4);
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

    private function isGroupValid(Group $group, int $maxAllowedDoubles): bool
    {
        try {
            $this->validator->validate($group, [
                new UniquePlacement(['maxAllowedDoubles' => $maxAllowedDoubles]),
                new RectangleGroup(),
                new RealisticSide(['piecesCount' => $this->piecesCount]),
                new PossibleSideMatching(['matchingMap' => $this->originalMatchingMap]),
            ]);

            return true;
        } catch (GroupInvalidException) {
            return false;
        }
    }
}
