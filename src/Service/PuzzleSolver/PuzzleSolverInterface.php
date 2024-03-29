<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\ReducedPiece;
use Bywulf\Jigsawlutioner\Dto\Solution;

interface PuzzleSolverInterface
{
    /**
     * @param array<int, Piece|ReducedPiece>      $pieces
     * @param array<string, array<string, float>> $matchingMap
     */
    public function findSolution(array $pieces, array $matchingMap): Solution;
}
