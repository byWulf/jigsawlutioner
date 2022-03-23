<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RectangleGroupValidator extends ConstraintValidator
{
    /**
     * @throws GroupInvalidException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RectangleGroup) {
            throw new UnexpectedTypeException($constraint, RectangleGroup::class);
        }

        if (!$value instanceof Group) {
            throw new UnexpectedTypeException($value, Group::class);
        }

        $limits = $this->getLimits($value);

        if (
            $this->hasNopsBelowMinimumXBorder($limits) ||
            $this->hasNopsBelowMinimumYBorder($limits) ||
            $this->hasNopsAboveMaximumXBorder($limits) ||
            $this->hasNopsAboveMaximumYBorder($limits)
        ) {
            throw new GroupInvalidException('No rectangle.');
        }
    }

    /**
     * @param array{minYBorder: int|null, minYNop: int|null, maxYBorder: int|null, maxYNop: int|null, minXBorder: int|null, minXNop: int|null, maxXBorder: int|null, maxXNop: int|null} $limits
     */
    private function hasNopsBelowMinimumYBorder(array $limits): bool
    {
        return $limits['minYNop'] !== null && $limits['minYBorder'] !== null && $limits['minYNop'] <= $limits['minYBorder'];
    }

    /**
     * @param array{minYBorder: int|null, minYNop: int|null, maxYBorder: int|null, maxYNop: int|null, minXBorder: int|null, minXNop: int|null, maxXBorder: int|null, maxXNop: int|null} $limits
     */
    private function hasNopsAboveMaximumYBorder(array $limits): bool
    {
        return $limits['maxYNop'] !== null && $limits['maxYBorder'] !== null && $limits['maxYNop'] >= $limits['maxYBorder'];
    }

    /**
     * @param array{minYBorder: int|null, minYNop: int|null, maxYBorder: int|null, maxYNop: int|null, minXBorder: int|null, minXNop: int|null, maxXBorder: int|null, maxXNop: int|null} $limits
     */
    private function hasNopsBelowMinimumXBorder(array $limits): bool
    {
        return $limits['minXNop'] !== null && $limits['minXBorder'] !== null && $limits['minXNop'] <= $limits['minXBorder'];
    }

    /**
     * @param array{minYBorder: int|null, minYNop: int|null, maxYBorder: int|null, maxYNop: int|null, minXBorder: int|null, minXNop: int|null, maxXBorder: int|null, maxXNop: int|null} $limits
     */
    private function hasNopsAboveMaximumXBorder(array $limits): bool
    {
        return $limits['maxXNop'] !== null && $limits['maxXBorder'] !== null && $limits['maxXNop'] >= $limits['maxXBorder'];
    }

    /**
     * @return array{minYBorder: int|null, minYNop: int|null, maxYBorder: int|null, maxYNop: int|null, minXBorder: int|null, minXNop: int|null, maxXBorder: int|null, maxXNop: int|null}
     */
    private function getLimits(Group $value): array
    {
        $limits = [
            'minYBorder' => null,
            'minYNop' => null,
            'maxYBorder' => null,
            'maxYNop' => null,
            'minXBorder' => null,
            'minXNop' => null,
            'maxXBorder' => null,
            'maxXNop' => null,
        ];
        foreach ($value->getPlacements() as $placement) {
            if ($value->getLastPlacementByPosition($placement->getX(), $placement->getY()) !== $placement) {
                continue;
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex())->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['minYBorder'] = max($limits['minYBorder'] ?? $placement->getY(), $placement->getY());
            } else {
                $limits['minYNop'] = min($limits['minYNop'] ?? $placement->getY(), $placement->getY());
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex() + 1)->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['minXBorder'] = max($limits['minXBorder'] ?? $placement->getX(), $placement->getX());
            } else {
                $limits['minXNop'] = min($limits['minXNop'] ?? $placement->getX(), $placement->getX());
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex() + 2)->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['maxYBorder'] = min($limits['maxYBorder'] ?? $placement->getY(), $placement->getY());
            } else {
                $limits['maxYNop'] = max($limits['maxYNop'] ?? $placement->getY(), $placement->getY());
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex() + 3)->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['maxXBorder'] = min($limits['maxXBorder'] ?? $placement->getX(), $placement->getX());
            } else {
                $limits['maxXNop'] = max($limits['maxXNop'] ?? $placement->getX(), $placement->getX());
            }
        }

        return $limits;
    }
}
