<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\ReducedPiece;
use Bywulf\Jigsawlutioner\Dto\Solution;

interface PuzzleSolverInterface
{
    /**
     * @param ReducedPiece[]                      $reducedPieces
     * @param array<string, array<string, float>> $matchingMap
     */
    public function findSolution(array $reducedPieces, array $matchingMap): Solution;
}
