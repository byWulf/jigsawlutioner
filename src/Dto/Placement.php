<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Placement
{
    public function __construct(
        private int $x,
        private int $y,
        private Piece $piece,
        private int $topSideIndex
    ) {
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function setX(int $x): Placement
    {
        $this->x = $x;
        return $this;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function setY(int $y): Placement
    {
        $this->y = $y;
        return $this;
    }

    public function getPiece(): Piece
    {
        return $this->piece;
    }

    public function getTopSideIndex(): int
    {
        return $this->topSideIndex;
    }

    public function setTopSideIndex(int $topSideIndex): Placement
    {
        $this->topSideIndex = $topSideIndex;
        return $this;
    }
}
