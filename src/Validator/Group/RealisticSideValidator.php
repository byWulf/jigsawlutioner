<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RealisticSideValidator extends ConstraintValidator
{
    /**
     * @throws GroupInvalidException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RealisticSide) {
            throw new UnexpectedTypeException($constraint, RealisticSide::class);
        }

        if (!$value instanceof Group) {
            throw new UnexpectedTypeException($value, Group::class);
        }

        $limits = $this->getLimits($value);

        $width = $limits['maxX'] - $limits['minX'];
        $height = $limits['maxY'] - $limits['minY'];
        if ($this->isUnrealisticSize($width, $height, $constraint->piecesCount, $limits)) {
            throw new GroupInvalidException('No realistic size.');
        }
    }

    /**
     * @param array{minYBorder: int|null, minY: int|null, maxYBorder: int|null, maxY: int|null, minXBorder: int|null, minX: int|null, maxXBorder: int|null, maxX: int|null} $limits
     */
    private function isUnrealisticSize(int $width, int $height, int $piecesCount, array $limits): bool
    {
        $minSize = (int) floor(sqrt($piecesCount) * 0.8);
        $maxSize = (int) ceil(sqrt($piecesCount) * 1.2);

        return
            $width > $maxSize ||
            $height > $maxSize ||
            ($width < $minSize && $limits['minXBorder'] !== null && $limits['maxXBorder'] !== null) ||
            ($height < $minSize && $limits['minYBorder'] !== null && $limits['maxYBorder'] !== null)
        ;
    }

    /**
     * @return array{minYBorder: int|null, minY: int|null, maxYBorder: int|null, maxY: int|null, minXBorder: int|null, minX: int|null, maxXBorder: int|null, maxX: int|null}
     */
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
            // Only take the new piece on this position, because the already existing piece will be deleted if it fits
            if ($value->getLastPlacementByPosition($placement->getX(), $placement->getY()) !== $placement) {
                continue;
            }

            $limits['minX'] = $limits['minX'] !== null ? min($limits['minX'], $placement->getX()) : $placement->getX();
            $limits['maxX'] = $limits['maxX'] !== null ? max($limits['maxX'], $placement->getX()) : $placement->getX();
            $limits['minY'] = $limits['minY'] !== null ? min($limits['minY'], $placement->getY()) : $placement->getY();
            $limits['maxY'] = $limits['maxY'] !== null ? max($limits['maxY'], $placement->getY()) : $placement->getY();

            $limits = $this->updateBorderLimits($limits, $placement);
        }

        return $limits;
    }

    /**
     * @param array{minYBorder: int|null, minY: int|null, maxYBorder: int|null, maxY: int|null, minXBorder: int|null, minX: int|null, maxXBorder: int|null, maxX: int|null} $limits
     *
     * @return array{minYBorder: int|null, minY: int|null, maxYBorder: int|null, maxY: int|null, minXBorder: int|null, minX: int|null, maxXBorder: int|null, maxX: int|null}
     */
    private function updateBorderLimits(array $limits, Placement $placement): array
    {
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

        return $limits;
    }
}
