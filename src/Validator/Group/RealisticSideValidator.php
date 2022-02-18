<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RealisticSideValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RealisticSide) {
            throw new UnexpectedTypeException($constraint, RealisticSide::class);
        }

        if (!$value instanceof Group) {
            throw new UnexpectedTypeException($value, Group::class);
        }

        $limits = $this->getLimits($value);


        $minSize = sqrt($constraint->piecesCount) * 0.8;
        $maxSize = sqrt($constraint->piecesCount) * 1.2;

        $width = $limits['maxX'] - $limits['minX'];
        $height = $limits['maxY'] - $limits['minY'];
        if (
            $width > $maxSize ||
            $height > $maxSize ||
            ($width < $minSize && $limits['minXBorder'] !== null && $limits['maxXBorder'] !== null) ||
            ($height < $minSize && $limits['minYBorder'] !== null && $limits['maxYBorder'] !== null)
        ) {
            throw new GroupInvalidException('No realistic size.');
        }
    }

    private function getLimits(Group $value): array
    {
        $limits = [
            'minYBorder' => null,
            'maxYBorder' => null,
            'minXBorder' => null,
            'maxXBorder' => null,
            'minX' => null,
            'maxX' => null,
            'minY' => null,
            'maxY' => null,
        ];
        foreach ($value->getPlacements() as $placement) {
            $limits['minX'] = min($limits['minX'], $placement->getX());
            $limits['maxX'] = max($limits['maxX'], $placement->getX());
            $limits['minY'] = min($limits['minY'], $placement->getY());
            $limits['maxY'] = max($limits['maxY'], $placement->getY());

            if (
                $value->getFirstPlacementByPosition($placement->getX(), $placement->getY()) === $placement &&
                count($value->getPlacementsByPosition($placement->getX(), $placement->getY())) > 1
            ) {
                continue;
            }
            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex())->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['minYBorder'] = max($limits['minYBorder'] ?? $placement->getY(), $placement->getY());
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex() + 1)->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['minXBorder'] = max($limits['minXBorder'] ?? $placement->getX(), $placement->getX());
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex() + 2)->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['maxYBorder'] = min($limits['maxYBorder'] ?? $placement->getY(), $placement->getY());
            }

            $direction = $placement->getPiece()->getSide($placement->getTopSideIndex() + 3)->getDirection();
            if ($direction === DirectionClassifier::NOP_STRAIGHT) {
                $limits['maxXBorder'] = min($limits['maxXBorder'] ?? $placement->getX(), $placement->getX());
            }
        }

        return $limits;
    }
}
