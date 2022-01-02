<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\Service\PointService;

class Placement
{
    private float $width;
    private float $height;

    public function __construct(
        private int $x,
        private int $y,
        private Piece $piece,
        private int $topSideIndex
    ) {
        $pointService = new PointService();

        $topSide = $piece->getSide($topSideIndex);
        $leftSide = $piece->getSide($topSideIndex + 1);
        $bottomSide = $piece->getSide($topSideIndex + 2);
        $rightSide = $piece->getSide($topSideIndex + 3);

        $this->width = ($pointService->getDistanceBetweenPoints($topSide->getStartPoint(), $topSide->getEndPoint()) + $pointService->getDistanceBetweenPoints($bottomSide->getStartPoint(), $bottomSide->getEndPoint())) / 2;
        $this->height = ($pointService->getDistanceBetweenPoints($leftSide->getStartPoint(), $leftSide->getEndPoint()) + $pointService->getDistanceBetweenPoints($rightSide->getStartPoint(), $rightSide->getEndPoint())) / 2;
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

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }
}
