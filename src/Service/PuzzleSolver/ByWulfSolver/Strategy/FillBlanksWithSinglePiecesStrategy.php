<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FillBlanksWithSinglePiecesStrategy
{
    use ByWulfSolverTrait;

    private ValidatorInterface $validator;

    public function __construct()
    {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->validator = Validation::createValidator();
    }

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

        do {
            $bestPiece = null;
            $bestGroup = null;
            $bestPosition = null;
            $bestRotation = null;
            $bestRating = 0;
            foreach ($singlePieces as $piece) {
                foreach ($group->getPlacements() as $placement) {
                    foreach (ByWulfSolver::DIRECTION_OFFSETS as $direction => $offset) {
                        if ($placement->getPiece()->getSide($placement->getTopSideIndex() + $direction)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
                            continue;
                        }

                        $x = $placement->getX() + $offset['x'];
                        $y = $placement->getY() + $offset['y'];
                        if ($group->getPlacementByPosition($x, $y) !== null) {
                            continue;
                        }

                        for ($rotation = 0; $rotation < 4; $rotation++) {
                            $rating = 0;
                            $connectedSides = 0;
                            foreach (ByWulfSolver::DIRECTION_OFFSETS as $placeDirection => $placeOffset) {
                                $oppositePlacement = $group->getPlacementByPosition($x + $placeOffset['x'], $y + $placeOffset['y']);
                                if ($oppositePlacement === null) {
                                    continue;
                                }

                                $sideDirection = $piece->getSide($rotation + $placeDirection)->getDirection();
                                $oppositeDirection = $oppositePlacement->getPiece()->getSide($oppositePlacement->getTopSideIndex() + 2 + $placeDirection)->getDirection();

                                if ($sideDirection === DirectionClassifier::NOP_STRAIGHT || $oppositeDirection === DirectionClassifier::NOP_STRAIGHT || $sideDirection === $oppositeDirection) {
                                    $rating = 0;
                                    break;
                                }

                                $sideMatchingProbability = $context->getOriginalMatchingProbability($this->getKey($piece->getIndex(), $rotation + $placeDirection), $this->getKey($oppositePlacement->getPiece()->getIndex(), $oppositePlacement->getTopSideIndex() + 2 + $placeDirection));
                                if ($sideMatchingProbability === 0.0) {
                                    $rating = 0;
                                    break;
                                }


                                $rating += $sideMatchingProbability;
                                $connectedSides++;
                            }
                            if ($rating === 0 || $connectedSides <= 1) {
                                continue;
                            }

                            $placement = new Placement($x, $y, $piece, $rotation);
                            $group->addPlacement($placement);
                            $isValidGroup = $this->isGroupValid($group, 0, $context->getPiecesCount());
                            $rating = $rating + (mt_rand() / mt_getrandmax()) * $variationFactor;

                            if ($rating > $bestRating && $isValidGroup) {
                                $bestPiece = $piece;
                                $bestGroup = $group;
                                $bestPosition = ['x' => $x, 'y' => $y];
                                $bestRotation = $rotation;
                                $bestRating = $rating;
                            }

                            $group->removePlacement($placement);
                        }
                    }
                }
            }

            if ($bestPiece !== null) {
                $bestGroup->addPlacement(new Placement($bestPosition['x'], $bestPosition['y'], $bestPiece, $bestRotation));

                $index = array_search($bestPiece, $singlePieces, true);
                if ($index !== false) {
                    unset($singlePieces[$index]);
                }

                $this->outputProgress($context, $outputMessage);
            }

        } while ($bestPiece !== null);
    }
}
