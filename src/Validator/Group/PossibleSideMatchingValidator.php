<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PossibleSideMatchingValidator extends ConstraintValidator
{
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
                $sideKey = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $indexOffset);

                $matchedPlacement = $value->getFirstPlacementByPosition($placement->getX() + $positionOffset['x'], $placement->getY() + $positionOffset['y']);
                if ($matchedPlacement === null) {
                    continue;
                }

                $matchedSideKey = $this->getKey($matchedPlacement->getPiece()->getIndex(), $matchedPlacement->getTopSideIndex() + 6 + $indexOffset);
                if (!isset($constraint->matchingMap[$sideKey][$matchedSideKey])) {
                    throw new GroupInvalidException('Side matching without probability.');
                }

                if ($constraint->matchingMap[$sideKey][$matchedSideKey] === 0.0) {
                    throw new GroupInvalidException('Side matching with probability of 0.');
                }
            }
        }
    }

    private function getKey(int|string $pieceIndex, int $sideIndex): string
    {
        return $pieceIndex . '_' . (($sideIndex + 4) % 4);
    }
}
