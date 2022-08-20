<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Validator\Group;

use Symfony\Component\Validator\Constraint;

class RealisticSize extends Constraint
{
    public int $piecesCount;
}
