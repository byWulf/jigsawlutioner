<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\ReducedPiece;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;

class FillBlanksWithSinglePiecesStrategy
{
    use ByWulfSolverTrait;

    public function execute(ByWulfSolverContext $context, Group $group, bool $canPlaceAboveExistingPlacement, float $variationFactor = 0): void
    {
        if ($this->shouldSkipStep($context)) {
            return;
        }

        $outputMessage = 'Trying to fit single pieces to the biggest group (using a variationFactor of ' . $variationFactor . '; ' . ($canPlaceAboveExistingPlacement ? 'overwriting enabled' : 'overwriting disabled') . ')...';
        $this->outputProgress($context, $outputMessage);

        /** @var ReducedPiece[] $singlePieces */
        $singlePieces = [];

        foreach ($context->getSolution()->getGroups() as $singleGroup) {
            if (count($singleGroup->getPlacements()) === 1) {
                $singlePieces[] = $singleGroup->getFirstPlacement()?->getPiece();
                $context->getSolution()->removeGroup($singleGroup);
            }
        }
        $singlePieces = array_filter($singlePieces);

        $count = count($singlePieces);

        for ($i = 0; $i < $count; $i++) {
            $bestPlacement = $this->getBestPlacement($singlePieces, $group, $context, $variationFactor, $canPlaceAboveExistingPlacement);
            if ($bestPlacement === null) {
                break;
            }

            $existingPlacement = $group->getPlacementByPosition($bestPlacement->getX(), $bestPlacement->getY());
            if ($existingPlacement !== null) {
                $group->removePlacement($existingPlacement);
            }

            $group->addPlacement($bestPlacement);

            foreach ($singlePieces as $index => $piece) {
                if ($piece->getIndex() === $bestPlacement->getPiece()->getIndex()) {
                    unset($singlePieces[$index]);
                    break;
                }
            }

            $this->outputProgress($context, $outputMessage);
        }

        $this->reportSolution($context);
    }

    /**
     * @param ReducedPiece[] $singlePieces
     */
    private function getBestPlacement(array $singlePieces, Group $group, ByWulfSolverContext $context, float $variationFactor, bool $canPlaceAboveExistingPlacement): ?Placement
    {
        $bestRating = 0;
        $bestPlacement = null;

        for ($x = $group->getMinX() - 1; $x <= $group->getMaxX() + 1; ++$x) {
            for ($y = $group->getMinY() - 1; $y <= $group->getMaxY() + 1; ++$y) {
                if (!$group->hasConnectingPlacement($x, $y)) {
                    continue;
                }

                if (!$canPlaceAboveExistingPlacement && $group->getPlacementByPosition($x, $y) !== null) {
                    continue;
                }

                foreach ($singlePieces as $piece) {
                    $rating = 0;
                    $targetPlacement = $this->getBestRatedPlacement($piece, $group, $x, $y, $context, $variationFactor, $rating);
                    if ($targetPlacement !== null && $rating > $bestRating) {
                        $bestRating = $rating;
                        $bestPlacement = $targetPlacement;
                    }
                }

            }
        }

        return $bestPlacement;
    }

    private function getBestRatedPlacement(ReducedPiece $piece, Group $group, int $x, int $y, ByWulfSolverContext $context, float $variationFactor, float &$rating): ?Placement
    {
        $bestRating = 0.0;
        $bestPlacement = null;
        for ($rotation = 0; $rotation < 4; ++$rotation) {
            $connectedSides = 0;
            $checkRating = $this->getConnectionRating($piece, $group, $x, $y, $rotation, $context, $connectedSides);
            if ($checkRating === 0.0 || $connectedSides < 2) {
                continue;
            }

            $placement = new Placement($x, $y, $piece, $rotation);
            $group->addPlacement($placement);
            $isValidRectangle = $this->isValidRectangle($group);
            $checkRating = $checkRating + (mt_rand() / mt_getrandmax()) * $variationFactor;

            if ($checkRating > $bestRating && $isValidRectangle) {
                $bestRating = $checkRating;
                $bestPlacement = new Placement($x, $y, $piece, $rotation);
            }

            $group->removePlacement($placement);
        }

        $rating = $bestRating;

        return $bestPlacement;
    }

    private function getConnectionRating(ReducedPiece $piece, Group $group, int $x, int $y, int $rotation, ByWulfSolverContext $context, int &$connectedSides): float
    {
        $rating = 0;
        foreach (ByWulfSolver::DIRECTION_OFFSETS as $direction => $offset) {
            $oppositePlacement = $group->getPlacementByPosition($x + $offset['x'], $y + $offset['y']);
            if ($oppositePlacement === null) {
                continue;
            }

            $sideDirection = $piece->getSide($rotation + $direction)->getDirection();
            $oppositeDirection = $oppositePlacement->getPiece()->getSide($oppositePlacement->getTopSideIndex() + 2 + $direction)->getDirection();

            if ($sideDirection === DirectionClassifier::NOP_STRAIGHT || $oppositeDirection === DirectionClassifier::NOP_STRAIGHT || $sideDirection === $oppositeDirection) {
                return 0.0;
            }

            $sideMatchingProbability = $context->getOriginalMatchingProbability($this->getKey($piece->getIndex(), $rotation + $direction), $this->getKey($oppositePlacement->getPiece()->getIndex(), $oppositePlacement->getTopSideIndex() + 2 + $direction));
            if ($sideMatchingProbability === 0.0) {
                return 0.0;
            }

            $rating += $sideMatchingProbability;
            ++$connectedSides;
        }

        return $rating;
    }
}
