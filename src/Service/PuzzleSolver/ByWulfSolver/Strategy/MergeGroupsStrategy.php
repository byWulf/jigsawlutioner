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
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\GroupRepositioner;

class MergeGroupsStrategy
{
    use ByWulfSolverTrait;

    private GroupRepositioner $groupRepositioner;

    public function __construct()
    {
        $this->groupRepositioner = new GroupRepositioner();
    }

    /**
     * @throws PuzzleSolverException
     */
    public function execute(ByWulfSolverContext $context, float $minProbability): void
    {
        $outputMessage = 'Merging groups with minProbability of ' . $minProbability . '...';
        $this->outputProgress($context, $outputMessage);

        while ($this->addNextPlacement($context, $minProbability)) {
            $this->outputProgress($context, $outputMessage);
        }
    }

    /**
     * @throws PuzzleSolverException
     */
    private function addNextPlacement(ByWulfSolverContext $context, float $minProbability): bool
    {
        $adjustedGroup = null;
        $nextKey = $this->getMostFittableGroupSide($context, $minProbability, $adjustedGroup);
        if ($nextKey === null) {
            return false;
        }

        $nextPieceIndex = $this->getPieceIndexFromKey($nextKey);

        $matchingKey = array_key_first($context->getMatchingProbabilities($nextKey)) ?? '';
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
            $matchingKey = array_key_first($context->getMatchingProbabilities($key)) ?? '';
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
            $group2Copy = $this->groupRepositioner->getRepositionedGroup($context, $group1, $group2, $key, $matchingKey, $minProbability, $probabilities, $bestRating);
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

    private function mergeGroups(Solution $solution, bool $removingAllowed, ?Group $group1, ?Group $group2, Group $adjustedGroup): void
    {
        if ($group1 === null || $group2 === null) {
            return;
        }

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
