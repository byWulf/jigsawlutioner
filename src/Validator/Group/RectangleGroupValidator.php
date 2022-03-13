<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use JsonException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RectangleGroupValidator extends ConstraintValidator
{
    /**
     * @throws GroupInvalidException
     * @throws JsonException
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
            ($limits['minYNop'] !== null && $limits['minYBorder'] !== null && $limits['minYNop'] <= $limits['minYBorder']) ||
            ($limits['maxYNop'] !== null && $limits['maxYBorder'] !== null && $limits['maxYNop'] >= $limits['maxYBorder']) ||
            ($limits['minXNop'] !== null && $limits['minXBorder'] !== null && $limits['minXNop'] <= $limits['minXBorder']) ||
            ($limits['maxXNop'] !== null && $limits['maxXBorder'] !== null && $limits['maxXNop'] >= $limits['maxXBorder'])
        ) {
            throw new GroupInvalidException('No rectangle.');
        }
    }

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
