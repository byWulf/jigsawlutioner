<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Placement
{
    public function __construct(
        private Piece $piece,
        private int $topSideIndex
    ) {
    }

    public function getPiece(): Piece
    {
        return $this->piece;
    }

    public function getTopSideIndex(): int
    {
        return $this->topSideIndex;
    }
}
