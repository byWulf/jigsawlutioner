<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;

class FillBlanksWithSinglePiecesStrategy
{
    use ByWulfSolverTrait;

    public function execute(ByWulfSolverContext $context, Group $group, float $variationFactor = 0): void
    {
        $outputMessage = 'Trying to fit single pieces to the biggest group (using a variationFactor of ' . $variationFactor . '...';
        $this->outputProgress($context, $outputMessage);

        $singlePieces = [];

        foreach ($context->getSolution()->getGroups() as $singleGroup) {
            if (count($singleGroup->getPlacements()) === 1) {
                $singlePieces[] = $singleGroup->getFirstPlacement()?->getPiece();
                $context->getSolution()->removeGroup($singleGroup);
            }
        }
        $singlePieces = array_filter($singlePieces);

        do {
            $bestPlacement = $this->getBestPlacement($singlePieces, $group, $context, $variationFactor);

            if ($bestPlacement !== null) {
                $group->addPlacement($bestPlacement);

                $index = array_search($bestPlacement->getPiece(), $singlePieces, true);
                if ($index !== false) {
                    unset($singlePieces[$index]);
                }

                $this->outputProgress($context, $outputMessage);
            }
        } while ($bestPlacement !== null);
    }

    /**
     * @param Piece[] $singlePieces
     */
    private function getBestPlacement(array $singlePieces, Group $group, ByWulfSolverContext $context, float $variationFactor): ?Placement
    {
        $bestRating = 0;
        $bestPlacement = null;
        foreach ($group->getPlacements() as $existingPlacement) {
            foreach (ByWulfSolver::DIRECTION_OFFSETS as $direction => $offset) {
                if ($existingPlacement->getPiece()->getSide($existingPlacement->getTopSideIndex() + $direction)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
                    continue;
                }

                $x = $existingPlacement->getX() + $offset['x'];
                $y = $existingPlacement->getY() + $offset['y'];
                if ($group->getPlacementByPosition($x, $y) !== null) {
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

    private function getBestRatedPlacement(Piece $piece, Group $group, int $x, int $y, ByWulfSolverContext $context, float $variationFactor, float &$rating): ?Placement
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
            $isValidGroup = $this->isGroupValid($group, 0, $context->getPiecesCount());
            $checkRating = $checkRating + (mt_rand() / mt_getrandmax()) * $variationFactor;

            if ($checkRating > $bestRating && $isValidGroup) {
                $bestRating = $checkRating;
                $bestPlacement = new Placement($x, $y, $piece, $rotation);
            }

            $group->removePlacement($placement);
        }

        $rating = $bestRating;

        return $bestPlacement;
    }

    private function getConnectionRating(Piece $piece, Group $group, int $x, int $y, int $rotation, ByWulfSolverContext $context, int &$connectedSides): float
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
