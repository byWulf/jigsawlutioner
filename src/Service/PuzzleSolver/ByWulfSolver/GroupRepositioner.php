<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;

class GroupRepositioner
{
    use ByWulfSolverTrait;

    /**
     * @param float[] $probabilities
     *
     * @throws PuzzleSolverException
     */
    public function getRepositionedGroup(ByWulfSolverContext $context, Group $group1, Group $group2, string $key1, string $key2, float $minProbability, array &$probabilities, float $bestRating): ?Group
    {
        $piece1 = $context->getPiece($this->getPieceIndexFromKey($key1));
        $piece2 = $context->getPiece($this->getPieceIndexFromKey($key2));

        // First clone the groups to manipulate the clones for testing the fittment
        $group1Copy = clone $group1;
        $group2Copy = clone $group2;

        $this->repositionGroup(
            $group2Copy,
            $group1Copy->getPlacementByPiece($piece1) ?? throw new PuzzleSolverException('Something went wrong.'),
            $this->getSideIndexFromKey($key1),
            $group2Copy->getPlacementByPiece($piece2) ?? throw new PuzzleSolverException('Something went wrong.'),
            $this->getSideIndexFromKey($key2)
        );

        $this->removeBadPieces($group1Copy, $group2Copy, $context);
        $group1Copy->addPlacementsFromGroup($group2Copy);

        $unmatchingSides = 0;

        //Check that the connecting sides have a matching probability of > 0.5
        $probabilities = $this->getMatchingProbabilities($group2Copy, $group1, $context, $minProbability, $unmatchingSides);

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

    private function repositionGroup(Group $group, Placement $placement1, int $sideIndex1, Placement $placement2, int $sideIndex2): void
    {
        $neededRotations = $placement1->getTopSideIndex() - $sideIndex1 - $placement2->getTopSideIndex() + $sideIndex2 + 2;
        $group->rotate($neededRotations);

        $targetX = $placement1->getX() + ByWulfSolver::DIRECTION_OFFSETS[($sideIndex1 - $placement1->getTopSideIndex() + 4) % 4]['x'];
        $targetY = $placement1->getY() + ByWulfSolver::DIRECTION_OFFSETS[($sideIndex1 - $placement1->getTopSideIndex() + 4) % 4]['y'];
        $group->move($targetX - $placement2->getX(), $targetY - $placement2->getY());
    }

    private function removeBadPieces(Group $group1, Group $group2, ByWulfSolverContext $context): void
    {
        if (!$context->isRemovingAllowed()) {
            return;
        }

        $group1PlacementsToBeRemoved = [];
        $group2PlacementsToBeRemoved = [];
        foreach ($group1->getPlacements() as $placement) {
            foreach (ByWulfSolver::DIRECTION_OFFSETS as $direction => $offset) {
                $oppositePlacement = $group2->getLastPlacementByPosition($placement->getX() + $offset['x'], $placement->getY() + $offset['y']);
                if ($oppositePlacement === null) {
                    continue;
                }
                $placementDirection = $placement->getPiece()->getSide($placement->getTopSideIndex() + $direction)->getDirection();
                $oppositeDirection = $oppositePlacement->getPiece()->getSide($oppositePlacement->getTopSideIndex() + 2 + $direction)->getDirection();

                if ($placementDirection === $oppositeDirection) {
                    $group1PlacementsToBeRemoved[] = $placement;
                    $group2PlacementsToBeRemoved[] = $oppositePlacement;
                }
            }
        }
        $group1->removePlacements($group1PlacementsToBeRemoved);
        $group2->removePlacements($group2PlacementsToBeRemoved);
    }

    /**
     * @return float[]
     */
    private function getMatchingProbabilities(Group $group, Group $targetGroup, ByWulfSolverContext $context, float $minProbability, int &$unmatchingSides): array
    {
        $probabilities = [];

        foreach ($group->getPlacements() as $placement) {
            for ($sideOffset = 0; $sideOffset < 4; ++$sideOffset) {
                $ownNeighbour = $group->getPlacementByPosition(
                    $placement->getX() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if ($ownNeighbour) {
                    continue;
                }

                $connectingPlacement = $targetGroup->getPlacementByPosition(
                    $placement->getX() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['x'],
                    $placement->getY() + ByWulfSolver::DIRECTION_OFFSETS[$sideOffset]['y']
                );
                if (!$connectingPlacement) {
                    continue;
                }

                $checkKey1 = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $sideOffset);
                $checkKey2 = $this->getKey($connectingPlacement->getPiece()->getIndex(), $connectingPlacement->getTopSideIndex() + $sideOffset + 2);

                if ($context->getOriginalMatchingProbability($checkKey1, $checkKey2) < $minProbability * 0.5) {
                    ++$unmatchingSides;
                }

                $probabilities[] = $context->getOriginalMatchingProbability($checkKey1, $checkKey2);
            }
        }

        return $probabilities;
    }
}
