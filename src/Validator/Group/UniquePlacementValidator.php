<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
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

        $foundDoubles = 0;
        foreach ($value->getPlacements() as $index => $placement) {
            foreach ($value->getPlacements() as $compareIndex => $comparePlacement) {
                if ($compareIndex <= $index) {
                    continue;
                }

                if ($placement->getX() === $comparePlacement->getX() && $placement->getY() === $comparePlacement->getY()) {
                    $foundDoubles++;
                    if ($foundDoubles > $constraint->maxAllowedDoubles) {
                        throw new GroupInvalidException('Doubled placements.');
                    }
                }
            }
        }
    }
}
