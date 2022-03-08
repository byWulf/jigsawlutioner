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
use Bywulf\Jigsawlutioner\Service\SolutionOutputter;
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

    /**
     * @var Piece[]
     */
    private array $pieces;

    private int $piecesCount;

    private array $matchingMap;

    private array $originalMatchingMap;

    private FilesystemAdapter $cache;

    private ValidatorInterface $validator;

    private SolutionOutputter $solutionOutputter;

    private string $cacheName;

    private int $step;

    private array $ignoredSideKeys = [];

    private array $tracedPieces = [];

    private bool $allowRemovingWhileMerging = true;

    private bool $outputStepSaving = false;

    public function __construct(
        private SideMatcherInterface $sideMatcher,
        private ?LoggerInterface $logger = null
    ) {
        $this->cache = new FilesystemAdapter(directory: __DIR__ . '/../../../resources/cache');
        $this->validator = Validation::createValidator();
        $this->solutionOutputter = new SolutionOutputter();
    }

    public function setIgnoredSideKeys(array $ignoredSideKeys): void
    {
        $this->ignoredSideKeys = $ignoredSideKeys;
    }

    public function addTracedPieceIndex(int $pieceIndex): void
    {
        $this->tracedPieces[$pieceIndex] = $pieceIndex;
    }

    /**
     * @param Piece[] $pieces
     */
    public function findSolution(array $pieces, string $cacheName = null, bool $useCache = true): Solution
    {
        $this->solution = new Solution();
        $this->pieces = $pieces;
        $this->piecesCount = count($pieces);
        $this->step = 0;
        $this->allowRemovingWhileMerging = true;

        if ($cacheName === null) {
            throw new InvalidArgumentException('$cacheName has to be given.');
        }

        $this->cacheName = $cacheName;

        $cacheKey = sha1(__CLASS__ . '::matchingMap_' . $cacheName);
        if (!$useCache) {
            $this->cache->delete($cacheKey);
            $this->cache->commit();
        }

        $this->originalMatchingMap = $this->cache->get($cacheKey, function() {
            $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Creating matching probability map...');;
            return $this->getMatchingMap();
        });

        foreach ($this->ignoredSideKeys as $ignoredSideKeyCombination) {
            [$sideKey, $matchingSideKey] = explode('-', $ignoredSideKeyCombination);

            unset($this->originalMatchingMap[$sideKey][$matchingSideKey]);
        }

        $this->matchingMap = $this->originalMatchingMap;

        $this->addPossiblePlacements(0.8, 0.5);
        $this->mergePossibleGroups(0.8, 0.5);

        $this->addPossiblePlacements(0.6, 0.25);
        $this->mergePossibleGroups(0.6, 0.25);

        $this->addPossiblePlacements(0.5, 0.1);
        $this->mergePossibleGroups(0.5, 0.1);

        $this->addPossiblePlacements(0.01, 0.01);
        $this->mergePossibleGroups(0.01, 0.01);

        $this->removeBadPieces(0.5);

        $this->addPossiblePlacements(0.5, 0.1);
        $this->mergePossibleGroups(0.5, 0.1);

        $this->removeBadPieces(0.2);

        $this->repeatedlyAddPossiblePlacements(0.01, 0.01);
        $this->repeatedlyAddPossiblePlacements(0, 0);

        $this->allowRemovingWhileMerging = false;
        $this->moveUnassignedPiecesToSingleGroups();
        $this->repeatedlyAddPossiblePlacements(0, 0);

        $this->moveUnassignedPiecesToSingleGroups();
        $this->repeatedlyAddPossiblePlacements(0, 0);

        $this->moveUnassignedPiecesToSingleGroups();

        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Finished creating solution. Placed ' . $this->solution->getPieceCount() . ' pieces in ' . count($this->solution->getGroups()) . ' groups.');

        $groups = $this->solution->getGroups();
        usort($groups, fn(Group $a, Group $b): int => count($b->getPlacements()) <=> count($a->getPlacements()));
        $this->solution->setGroups($groups);

        $this->setPlacementContexts();

        return $this->solution;
    }

    private function repeatedlyAddPossiblePlacements(float $minProbability, float $minDifference): void
    {
        $lastPieceCount = $this->solution->getPieceCount();
        $lastGroupCount = count($this->solution->getGroups());
        for ($i = 0; $i < 5; $i++) {
            $this->matchingMap = $this->originalMatchingMap;
            $this->addPossiblePlacements($minProbability, $minDifference);
            $this->mergePossibleGroups($minProbability, $minDifference);

            if ($this->solution->getPieceCount() === $lastPieceCount && count($this->solution->getGroups()) === $lastGroupCount) {
                break;
            }
            $lastPieceCount = $this->solution->getPieceCount();
            $lastGroupCount = count($this->solution->getGroups());
        }
    }

    private function addPossiblePlacements(float $minProbability, float $minDifference): void
    {
        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Adding new pieces with minProbability of ' . $minProbability . '/' . $minDifference . '...');

        while ($this->addNextPlacement([$this, 'getMostFittableSide'], $minProbability, $minDifference)) {
            $this->logger?->debug((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Placed ' . $this->solution->getPieceCount() . ' pieces in ' . count($this->solution->getGroups()) . ' groups (add).');

            $this->saveOutputStep();
        }
    }

    private function mergePossibleGroups(float $minProbability, float $minDifference): void
    {
        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Merging groups with minProbability of ' . $minProbability . '/' . $minDifference . '...');

        while ($this->addNextPlacement([$this, 'getMostFittableGroupSide'], $minProbability, $minDifference)) {
            $this->logger?->debug((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Placed ' . $this->solution->getPieceCount() . ' pieces in ' . count($this->solution->getGroups()) . ' groups (reduce).');

            $this->saveOutputStep();
        }
    }

    private function saveOutputStep(): void
    {
        if (!$this->outputStepSaving) {
            return;
        }

        $this->setPlacementContexts();

        $this->solutionOutputter->outputAsHtml(
            $this->solution,
            __DIR__ . '/../../../resources/Fixtures/Set/' . $this->cacheName . '/solution_' . $this->step . '.html',
            __DIR__ . '/../../../resources/Fixtures/Set/' . $this->cacheName . '/piece%s_transparent_small.png',
            previousFile: $this->step === 0 ? null : (__DIR__ . '/../../../resources/Fixtures/Set/' . $this->cacheName . '/solution_' . ($this->step - 1) . '.html'),
            nextFile: __DIR__ . '/../../../resources/Fixtures/Set/' . $this->cacheName . '/solution_' . ($this->step + 1) . '.html',
            ignoredSideKeys: $this->ignoredSideKeys
        );

        $this->step++;
    }

    private function addNextPlacement(callable $nextKeyGetter, float $minProbability, float $minDifference, bool $createNewGroups = true): bool
    {
        $context = null;
        $nextKey = $nextKeyGetter($minProbability, $minDifference, $context);
        if ($nextKey === null) {
            return false;
        }

        $nextPieceIndex = $this->getPieceIndexFromKey($nextKey);

        $matchingKey = array_key_first($this->matchingMap[$nextKey]);
        $matchingPieceIndex = $this->getPieceIndexFromKey($matchingKey);

        $this->logPieceDecision([$nextPieceIndex, $matchingPieceIndex], 'Is next placement!', ['nextKey' => $nextKey, 'matchingKey' => $matchingKey]);

        $groups = $this->getGroups([$nextPieceIndex, $matchingPieceIndex]);
        if (count($groups) === 0) {
            if ($createNewGroups) {
                $group = new Group();
                $this->solution->addGroup($group);
                $group->addPlacement(new Placement(0, 0, $this->pieces[$nextPieceIndex], 0));
                $this->logPieceDecision([$nextPieceIndex], 'Created new ' . $group . ' with this piece.');

                $this->addPlacementToGroup($group, $nextKey, $matchingKey, $minProbability, $minDifference);
            }
        } elseif (count($groups) === 1) {
            $this->addPlacementToGroup(reset($groups), $nextKey, $matchingKey, $minProbability, $minDifference);
        } elseif (count($groups) === 2) {
            $this->mergeGroups($groups[$nextPieceIndex], $groups[$matchingPieceIndex], $nextKey, $matchingKey, $minProbability, $context);
        } else {
            throw new PuzzleSolverException('Expected 0 to 2 groups, got ' . count($groups) . '.');
        }

        unset($this->matchingMap[$nextKey], $this->matchingMap[$matchingKey]);
        $this->logPieceDecision([$nextPieceIndex, $matchingPieceIndex], 'Removed probabilities from map.', ['keys' => [$nextKey, $matchingKey]]);

        return true;
    }

    private function addPlacementToGroup(Group $group, string $key1, string $key2, float $minProbability, float $minDifference): void
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

        $error = null;
        if (!$this->isGroupValid($group, 0, $error)) {
            // Because group is not valid now, we reset it and do nothing
            $group->removePlacement($placement);

            $this->logPieceDecision([$newPiece->getIndex()], 'Adding placement to ' . $group . ' failed.', ['error' => $error]);
        } else {
            $this->logPieceDecision([$newPiece->getIndex()], 'Added to ' . $group . ' due to adding.');
        }
    }

    private function mergeGroups(Group $group1, Group $group2, string $key1, string $key2, float $minProbability, mixed $context): void
    {
        if (!$context['group']) {
            $this->logPieceDecision([$this->getPieceIndexFromKey($key1), $this->getPieceIndexFromKey($key2)], 'Couldn\'t merge groups.');
            return;
        }

        $removedPiecesGroup1 = new Group();
        $removedPiecesGroup2 = new Group();

        foreach ($context['group']->getPlacements() as $placement) {
            $existingPlacement = $group1->getFirstPlacementByPosition($placement->getX(), $placement->getY());
            if ($existingPlacement && !$this->allowRemovingWhileMerging) {
                continue;
            }

            if ($existingPlacement) {
                $removedPiecesGroup1->addPlacement($existingPlacement);
                $removedPiecesGroup2->addPlacement($placement);

                $group1->removePlacement($existingPlacement);
                $this->logPieceDecision([$existingPlacement->getPiece()->getIndex()], 'Removed from ' . $group1 . ' due to merging.');
                continue;
            }

            $group1->addPlacement($placement);
            $this->logPieceDecision([$placement->getPiece()->getIndex()], 'Moved to ' . $group1 . ' due to merging.');
        }

        $this->solution->removeGroup($group2);

        if (count($removedPiecesGroup1->getPlacements()) > 0) {
            $this->solution->addGroup($removedPiecesGroup1);
            $this->solution->addGroup($removedPiecesGroup2);

            $this->splitSeparatedGroups($group1);
            $this->splitSeparatedGroups($removedPiecesGroup1);
            $this->splitSeparatedGroups($removedPiecesGroup2);
        }
    }

    private function getRepositionedGroup(Group $group1, Group $group2, string $key1, string $key2, float $minProbability, array &$probabilities, ?string &$failedReason, float $bestRating): ?Group
    {
        // If its the same groups, we cannot merge them
        if ($group1 === $group2) {
            $failedReason = 'same groups';
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

        $group1Copy = clone $group1;

        if ($this->allowRemovingWhileMerging) {
            foreach ($group1Copy->getPlacements() as $placement) {
                foreach (self::DIRECTION_OFFSETS as $direction => $offset) {
                    $oppositePlacement = $group2Copy->getLastPlacementByPosition($placement->getX() + $offset['x'], $placement->getY() + $offset['y']);
                    if ($oppositePlacement === null) {
                        continue;
                    }
                    $placementDirection = $placement->getPiece()->getSide($placement->getTopSideIndex() + $direction)->getDirection();
                    $oppositeDirection = $oppositePlacement->getPiece()->getSide($oppositePlacement->getTopSideIndex() + 2 + $direction)->getDirection();

                    if ($placementDirection === $oppositeDirection) {
                        $group1Copy->removePlacement($placement, false);
                        $group2Copy->removePlacement($oppositePlacement, false);
                    }
                }
            }
            $group1Copy->updateIndexedPlacements();
            $group2Copy->updateIndexedPlacements();
        }

        foreach ($group2Copy->getPlacements() as $placement) {
            $group1Copy->addPlacement($placement);
        }

        $unmatchingSides = 0;

        //Check that the connecting sides have a matching probability of > 0.5
        foreach ($group2Copy->getPlacements() as $placement) {
            for ($sideOffset = 0; $sideOffset < 4; $sideOffset++) {
                $ownNeighbour = $group2Copy->getPlacementByPosition(
                    $placement->getX() + self::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + self::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if ($ownNeighbour) {
                    continue;
                }

                $connectingPlacement = $group1->getPlacementByPosition(
                    $placement->getX() + self::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + self::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if (!$connectingPlacement) {
                    continue;
                }

                $checkKey1 = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $sideOffset);
                $checkKey2 = $this->getKey($connectingPlacement->getPiece()->getIndex(), $connectingPlacement->getTopSideIndex() + $sideOffset + 2);

                if (($this->originalMatchingMap[$checkKey1][$checkKey2] ?? 0) < $minProbability * 0.5) {
                    $unmatchingSides++;
                }

                $probabilities[] = $this->originalMatchingMap[$checkKey1][$checkKey2] ?? 0;
            }
        }

        if (array_sum($probabilities) <= $bestRating) {
            return null;
        }

        $maxUnmatchedSides = count($probabilities) * 0.1;
        if ($unmatchingSides > $maxUnmatchedSides) {
            $failedReason = 'Too many unmatched sides (having ' . $unmatchingSides . ', but only ' . $maxUnmatchedSides . ' would have been allowed)';
            return null;
        }

        $minConnectingSides = min(!$minProbability ? 1 : 5, min(count($group1->getPlacements()), count($group2->getPlacements())) * 0.5);
        if (count($probabilities) < $minConnectingSides) {
            $failedReason = 'Too few connecting sides (having ' . count($probabilities) . ', but we need at least ' . $minConnectingSides . ')';
            return null;
        }

        if ($this->isGroupValid($group1Copy, (int) round(count($group2Copy->getPlacements()) * 0.5), $failedReason)) {
            return $group2Copy;
        }

        return null;
    }

    private function setPlacementContexts(): void
    {
        foreach ($this->solution->getGroups() as $group) {
            foreach ($group->getPlacements() as $placement) {
                $context = [];
                foreach (self::DIRECTION_OFFSETS as $indexOffset => $positionOffset) {
                    $sideKey = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $indexOffset);

                    $matchedPlacement = $group->getPlacementByPosition($placement->getX() + $positionOffset['x'], $placement->getY() + $positionOffset['y']);
                    $matchedSideKey = null;
                    if ($matchedPlacement !== null) {
                        $matchedSideKey = $this->getKey($matchedPlacement->getPiece()->getIndex(), $matchedPlacement->getTopSideIndex() + 6 + $indexOffset);
                    }


                    $context[$indexOffset] = [
                        'probabilities' => $this->originalMatchingMap[$sideKey] ?? [],
                        'matchedProbabilityIndex' => $matchedSideKey !== null ? array_search($matchedSideKey, array_keys($this->originalMatchingMap[$sideKey] ?? [])) : null,
                        'matchingKey' => $sideKey . '-' . $matchedSideKey,
                    ];
                }
                $placement->setContext($context);
            }
        }
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
        $bestGroup = null;
        foreach (array_keys($this->matchingMap) as $key) {
            $matchingKey = array_key_first($this->matchingMap[$key]);
            if ($this->matchingMap[$key][$matchingKey] < $minProbability) {
                continue;
            }

            $group1 = $this->solution->getGroupByPiece($this->pieces[$this->getPieceIndexFromKey($key)]);
            if (!$group1) {
                continue;
            }

            $group2 = $this->solution->getGroupByPiece($this->pieces[$this->getPieceIndexFromKey($matchingKey)]);
            if (!$group2) {
                continue;
            }

            $probabilities = [];
            $failedReason = null;
            $group2Copy = $this->getRepositionedGroup($group1, $group2, $key, $matchingKey, $minProbability, $probabilities, $failedReason, $bestRating);

            if ($group2Copy === null) {
                $this->logPieceDecision([$this->getPieceIndexFromKey($key)], 'While searching for most fittable group, an error occured.', ['reason' => $failedReason, 'key' => $key]);
            } elseif (min($probabilities) < $minProbability) {
                $this->logPieceDecision([$this->getPieceIndexFromKey($key)], 'While searching for most fittable group, the lowest probability didn\'t meet the given minProbability.', ['configured limit' => $minProbability, 'min probability' => min($probabilities), 'key' => $key]);
            } elseif (array_sum($probabilities) > $bestRating) {
                $bestRating = array_sum($probabilities);
                $bestKey = $key;
                $bestGroup = $group2Copy;
            }
        }

        $context['group'] = $bestGroup;

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

        foreach ($pieceIndexes as $pieceIndex) {
            $group = $this->solution->getGroupByPiece($this->pieces[$pieceIndex]);
            if ($group !== null) {
                $foundGroups[$pieceIndex] = $group;
            }
        }

        return $foundGroups;
    }

    private function isGroupValid(Group $group, int $maxAllowedDoubles, &$error = null): bool
    {
        try {
            $this->validator->validate($group, [
                new UniquePlacement(['maxAllowedDoubles' => $maxAllowedDoubles]),
                new RealisticSide(['piecesCount' => $this->piecesCount]),
                new RectangleGroup(),
                new PossibleSideMatching(),
            ]);

            return true;
        } catch (GroupInvalidException $exception) {
            $error = $exception->getMessage();

            return false;
        }
    }

    private function logPieceDecision(array $pieceIndexes, string $message, array $context = []): void
    {
        $foundPieces = [];
        foreach ($pieceIndexes as $pieceIndex) {
            if (isset($this->tracedPieces[$pieceIndex])) {
                $foundPieces[] = $pieceIndex;
            }
        }

        if (count($foundPieces) > 0) {
            $this->logger->debug('[' . implode(', ', $foundPieces) . '] - Step ' . $this->step . ' - ' . $message . ' (' . json_encode($context) . ')');
        }
    }

    private function splitSeparatedGroups(Group $group): void
    {
        $checkGroup = clone $group;

        $newGroups = [];
        while ($placement = $checkGroup->getFirstPlacement()) {
            $newGroup = new Group();

            $this->movePlacementChainToGroup($placement, $checkGroup, $newGroup);

            $newGroups[] = $newGroup;
        }

        if (count($newGroups) > 1) {
            foreach ($newGroups as $newGroup) {
                $this->solution->addGroup($newGroup);
            }
            $this->solution->removeGroup($group);
        }

        foreach ($this->solution->getGroups() as $checkGroup) {
            if (count($checkGroup->getPlacements()) === 1) {
                $this->solution->removeGroup($checkGroup);
            }
        }
    }

    private function movePlacementChainToGroup(Placement $placement, Group $fromGroup, Group $toGroup): void
    {
        $fromGroup->removePlacement($placement);
        $toGroup->addPlacement($placement);

        foreach (self::DIRECTION_OFFSETS as $offset) {
            $nextPlacement = $fromGroup->getFirstPlacementByPosition($placement->getX() + $offset['x'], $placement->getY() + $offset['y']);

            if ($nextPlacement) {
                $this->movePlacementChainToGroup($nextPlacement, $fromGroup, $toGroup);
            }
        }
    }

    private function removeBadPieces(float $maxProbability): void
    {
        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Removing all pieces from solution, that have a connecting probability of ' . $maxProbability . ' or less...');

        foreach ($this->solution->getGroups() as $group) {
            $failedPlacements = [];
            foreach ($group->getPlacements() as $placement) {
                foreach (self::DIRECTION_OFFSETS as $direction => $offset) {
                    $oppositePlacement = $group->getFirstPlacementByPosition(
                        $placement->getX() + $offset['x'],
                        $placement->getY() + $offset['y']
                    );
                    if ($oppositePlacement === null) {
                        continue;
                    }

                    if (($this->originalMatchingMap[$this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $direction)][$this->getKey($oppositePlacement->getPiece()->getIndex(), $oppositePlacement->getTopSideIndex() + 2 + $direction)] ?? 0.0) <= $maxProbability) {
                        $failedPlacements[] = $oppositePlacement;
                        $failedPlacements[] = $placement;
                    }
                }
            }

            if (count($failedPlacements) > 0) {
                foreach ($failedPlacements as $failedPlacement) {
                    $group->removePlacement($failedPlacement);
                }

                if (count($group->getPlacements()) === 0) {
                    $this->solution->removeGroup($group);
                } else {
                    $this->splitSeparatedGroups($group);
                }

                $this->logPieceDecision(
                    array_map(fn(Placement $placement): int => $placement->getPiece()->getIndex(), $failedPlacements),
                    'Removed from solution due to bad connecting probability.'
                );
            }
        }
    }

    private function moveUnassignedPiecesToSingleGroups(): void
    {
        foreach ($this->pieces as $piece) {
            if ($this->solution->getGroupByPiece($piece)) {
                continue;
            }

            $group = new Group();
            $group->addPlacement(new Placement(0, 0, $piece, 0));
            $this->solution->addGroup($group);
            $this->logPieceDecision([$piece->getIndex()], 'Adding piece to new single ' . $group . ' due to finishing the solution.');
        }
    }
}
