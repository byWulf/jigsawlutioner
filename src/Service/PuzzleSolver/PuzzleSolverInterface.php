<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Solution;

interface PuzzleSolverInterface
{
    /**
     * @param Piece[] $pieces
     */
    public function findSolution(array $pieces): Solution;
}
