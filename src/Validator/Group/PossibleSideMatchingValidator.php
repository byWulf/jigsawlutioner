<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PossibleSideMatchingValidator extends ConstraintValidator
{
    /**
     * @throws GroupInvalidException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PossibleSideMatching) {
            throw new UnexpectedTypeException($constraint, PossibleSideMatching::class);
        }

        if (!$value instanceof Group) {
            throw new UnexpectedTypeException($value, Group::class);
        }

        foreach ($value->getPlacements() as $placement) {
            foreach (ByWulfSolver::DIRECTION_OFFSETS as $indexOffset => $positionOffset) {
                // Only take the new piece on this position, because the already existing piece will be deleted if it fits
                if ($value->getLastPlacementByPosition($placement->getX(), $placement->getY()) !== $placement) {
                    continue;
                }
                $side = $placement->getPiece()->getSide($placement->getTopSideIndex() + $indexOffset);

                $matchedPlacement = $value->getLastPlacementByPosition($placement->getX() + $positionOffset['x'], $placement->getY() + $positionOffset['y']);
                if ($matchedPlacement === null) {
                    continue;
                }
                $matchedSide = $matchedPlacement->getPiece()->getSide($matchedPlacement->getTopSideIndex() + 2 + $indexOffset);

                if (!$this->doSideDirectionsMatch($side, $matchedSide)) {
                    throw new GroupInvalidException('Side directions don\'t match.');
                }
            }
        }
    }

    private function doSideDirectionsMatch(Side $side1, Side $side2): bool
    {
        return
            $side1->getDirection() !== DirectionClassifier::NOP_STRAIGHT &&
            $side2->getDirection() !== DirectionClassifier::NOP_STRAIGHT &&
            $side1->getDirection() !== $side2->getDirection()
        ;
    }
}
