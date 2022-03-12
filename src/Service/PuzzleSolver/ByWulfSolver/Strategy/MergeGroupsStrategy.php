<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MergeGroupsStrategy
{
    use ByWulfSolverTrait;

    private ValidatorInterface $validator;

    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->validator = Validation::createValidator();
    }

    /**
     * @throws PuzzleSolverException
     */
    public function execute(ByWulfSolverContext $context, float $minProbability): void
    {
        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Merging groups with minProbability of ' . $minProbability . '...');

        while ($this->addNextPlacement($context, $minProbability)) {
            $this->outputProgress($context, 'MergeGroups');
        }
    }

    /**
     * @throws PuzzleSolverException
     */
    private function addNextPlacement(ByWulfSolverContext $context, float $minProbability): bool
    {
        $nextKey = $this->getMostFittableGroupSide($context, $minProbability, $adjustedGroup);
        if ($nextKey === null) {
            return false;
        }

        $nextPieceIndex = $this->getPieceIndexFromKey($nextKey);

        $matchingKey = array_key_first($context->getMatchingProbabilities($nextKey));
        $matchingPieceIndex = $this->getPieceIndexFromKey($matchingKey);

        $this->mergeGroups(
            $context->getSolution(),
            $context->isRemovingAllowed(),
            $context->getSolution()->getGroupByPiece($context->getPiece($nextPieceIndex)),
            $context->getSolution()->getGroupByPiece($context->getPiece($matchingPieceIndex)),
            $adjustedGroup
        );

        $context->unsetMatchingMapKey($nextKey);
        $context->unsetMatchingMapKey($matchingKey);

        return true;
    }

    /**
     * @throws PuzzleSolverException
     */
    private function getMostFittableGroupSide(ByWulfSolverContext $context, float $minProbability, ?Group &$adjustedGroup): ?string
    {
        $bestRating = 0;
        $bestKey = null;
        foreach (array_keys($context->getMatchingMap()) as $key) {
            $matchingKey = array_key_first($context->getMatchingProbabilities($key));
            if ($context->getMatchingProbability($key, $matchingKey) < $minProbability) {
                continue;
            }

            $group1 = $context->getSolution()->getGroupByPiece($context->getPiece($this->getPieceIndexFromKey($key)));
            if (!$group1) {
                continue;
            }

            $group2 = $context->getSolution()->getGroupByPiece($context->getPiece($this->getPieceIndexFromKey($matchingKey)));
            if (!$group2) {
                continue;
            }

            if ($group1 === $group2) {
                continue;
            }

            $probabilities = [];
            $group2Copy = $this->getRepositionedGroup($context, $group1, $group2, $key, $matchingKey, $minProbability, $probabilities, $bestRating);
            if ($group2Copy === null) {
                continue;
            }
            if (min($probabilities) < $minProbability) {
                continue;
            }

            if (array_sum($probabilities) > $bestRating) {
                $bestRating = array_sum($probabilities);
                $bestKey = $key;
                $adjustedGroup = $group2Copy;
            }
        }

        return $bestKey;
    }

    /**
     * @throws PuzzleSolverException
     */
    private function getRepositionedGroup(ByWulfSolverContext $context, Group $group1, Group $group2, string $key1, string $key2, float $minProbability, array &$probabilities, float $bestRating): ?Group
    {
        $piece1 = $context->getPiece($this->getPieceIndexFromKey($key1));
        $sideIndex1 = $this->getSideIndexFromKey($key1);
        $placement1 = $group1->getPlacementByPiece($piece1);

        if ($placement1 === null) {
            throw new PuzzleSolverException('Something went wrong.');
        }

        $piece2 = $context->getPiece($this->getPieceIndexFromKey($key2));
        $sideIndex2 = $this->getSideIndexFromKey($key2);

        // First clone the second group to manipulate the clone for testing the fittment
        $group2Copy = clone $group2;
        $placement2 = $group2Copy->getPlacementByPiece($piece2);

        if ($placement2 === null) {
            throw new PuzzleSolverException('Something went wrong.');
        }

        $neededRotations = $placement1->getTopSideIndex() - $sideIndex1 - $placement2->getTopSideIndex() + $sideIndex2 + 2;
        $group2Copy->rotate($neededRotations);

        $targetX = $placement1->getX() + ByWulfSolver::DIRECTION_OFFSETS[($sideIndex1 - $placement1->getTopSideIndex() + 4) % 4]['x'];
        $targetY = $placement1->getY() + ByWulfSolver::DIRECTION_OFFSETS[($sideIndex1 - $placement1->getTopSideIndex() + 4) % 4]['y'];
        $group2Copy->move($targetX - $placement2->getX(), $targetY - $placement2->getY());

        $group1Copy = clone $group1;

        if ($context->isRemovingAllowed()) {
            foreach ($group1Copy->getPlacements() as $placement) {
                foreach (ByWulfSolver::DIRECTION_OFFSETS as $direction => $offset) {
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
                    $placement->getX() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if ($ownNeighbour) {
                    continue;
                }

                $connectingPlacement = $group1->getPlacementByPosition(
                    $placement->getX() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if (!$connectingPlacement) {
                    continue;
                }

                $checkKey1 = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $sideOffset);
                $checkKey2 = $this->getKey($connectingPlacement->getPiece()->getIndex(), $connectingPlacement->getTopSideIndex() + $sideOffset + 2);

                if ($context->getOriginalMatchingProbability($checkKey1, $checkKey2) < $minProbability * 0.5) {
                    $unmatchingSides++;
                }

                $probabilities[] = $context->getOriginalMatchingProbability($checkKey1, $checkKey2);
            }
        }

        if (array_sum($probabilities) <= $bestRating) {
            return null;
        }

        $maxUnmatchedSides = count($probabilities) * 0.1;
        if ($unmatchingSides > $maxUnmatchedSides) {
            return null;
        }

        $minConnectingSides = min(!$minProbability ? 1 : 5, min(count($group1->getPlacements()), count($group2->getPlacements())) * 0.5);
        if (count($probabilities) < $minConnectingSides) {
            return null;
        }

        if ($this->isGroupValid($group1Copy, (int) round(count($group2Copy->getPlacements()) * 0.5), $context->getPiecesCount())) {
            return $group2Copy;
        }

        return null;
    }

    private function mergeGroups(Solution $solution, bool $removingAllowed, Group $group1, Group $group2, Group $adjustedGroup): void
    {
        $removedPiecesGroup1 = new Group();
        $removedPiecesGroup2 = new Group();

        foreach ($adjustedGroup->getPlacements() as $placement) {
            $existingPlacement = $group1->getFirstPlacementByPosition($placement->getX(), $placement->getY());
            if ($existingPlacement && !$removingAllowed) {
                continue;
            }

            if ($existingPlacement) {
                $removedPiecesGroup1->addPlacement($existingPlacement);
                $removedPiecesGroup2->addPlacement($placement);

                $group1->removePlacement($existingPlacement);
                continue;
            }

            $group1->addPlacement($placement);
        }

        $solution->removeGroup($group2);

        if (count($removedPiecesGroup1->getPlacements()) > 0) {
            $solution->addGroup($removedPiecesGroup1);
            $solution->addGroup($removedPiecesGroup2);

            $this->splitSeparatedGroups($solution, $group1);
            $this->splitSeparatedGroups($solution, $removedPiecesGroup1);
            $this->splitSeparatedGroups($solution, $removedPiecesGroup2);
        }
    }

    private function splitSeparatedGroups(Solution $solution, Group $group): void
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
                $solution->addGroup($newGroup);
            }
            $solution->removeGroup($group);
        }

        foreach ($solution->getGroups() as $checkGroup) {
            if (count($checkGroup->getPlacements()) === 1) {
                $solution->removeGroup($checkGroup);
            }
        }
    }

    private function movePlacementChainToGroup(Placement $placement, Group $fromGroup, Group $toGroup): void
    {
        $fromGroup->removePlacement($placement);
        $toGroup->addPlacement($placement);

        foreach (ByWulfSolver::DIRECTION_OFFSETS as $offset) {
            $nextPlacement = $fromGroup->getFirstPlacementByPosition($placement->getX() + $offset['x'], $placement->getY() + $offset['y']);

            if ($nextPlacement) {
                $this->movePlacementChainToGroup($nextPlacement, $fromGroup, $toGroup);
            }
        }
    }
}
