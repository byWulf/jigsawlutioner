<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class RemoveBadPiecesStrategy
{
    use ByWulfSolverTrait;

    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function execute(ByWulfSolverContext $context, float $maxProbability, int $minimumSidesBelow = 1): void
    {
        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Removing all pieces from solution, that have a connecting probability of ' . $maxProbability . ' or less on ' . $minimumSidesBelow . ' sides or more...');

        foreach ($context->getSolution()->getGroups() as $group) {
            $failedPlacements = [];
            foreach ($group->getPlacements() as $placement) {
                $sidesBelow = 0;
                foreach (ByWulfSolver::DIRECTION_OFFSETS as $direction => $offset) {
                    $oppositePlacement = $group->getFirstPlacementByPosition(
                        $placement->getX() + $offset['x'],
                        $placement->getY() + $offset['y']
                    );
                    if ($oppositePlacement === null) {
                        continue;
                    }

                    $probability = $context->getOriginalMatchingProbability(
                        $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $direction),
                        $this->getKey($oppositePlacement->getPiece()->getIndex(), $oppositePlacement->getTopSideIndex() + 2 + $direction)
                    );
                    if ($probability <= $maxProbability) {
                        $sidesBelow++;
                    }
                }
                if ($sidesBelow >= $minimumSidesBelow) {
                    $failedPlacements[] = $placement;
                }
            }

            if (count($failedPlacements) > 0) {
                foreach ($failedPlacements as $failedPlacement) {
                    $group->removePlacement($failedPlacement);
                }

                if (count($group->getPlacements()) === 0) {
                    $context->getSolution()->removeGroup($group);
                } else {
                    $this->splitSeparatedGroups($context, $group);
                }

                $this->outputProgress($context, 'RemoveBadPieces');
            }
        }
    }

    private function splitSeparatedGroups(ByWulfSolverContext $context, Group $group): void
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
                $context->getSolution()->addGroup($newGroup);
            }
            $context->getSolution()->removeGroup($group);
        }

        foreach ($context->getSolution()->getGroups() as $checkGroup) {
            if (count($checkGroup->getPlacements()) === 1) {
                $context->getSolution()->removeGroup($checkGroup);
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
