<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniquePlacementValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniquePlacement) {
            throw new UnexpectedTypeException($constraint, UniquePlacement::class);
        }

        if (!$value instanceof Group) {
            throw new UnexpectedTypeException($value, Group::class);
        }

        foreach ($value->getPlacements() as $placement) {
            foreach ($value->getPlacements() as $comparePlacement) {
                if ($placement === $comparePlacement) {
                    continue;
                }

                if ($placement->getX() === $comparePlacement->getX() && $placement->getY() === $comparePlacement->getY()) {
                    $this->context->buildViolation('doubled placements')->addViolation();
                }
            }
        }
    }
}
