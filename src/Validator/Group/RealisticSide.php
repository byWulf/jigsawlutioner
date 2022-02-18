<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Symfony\Component\Validator\Constraint;

class RealisticSide extends Constraint
{
    public int $piecesCount;
}
