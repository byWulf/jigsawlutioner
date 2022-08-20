<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\Service\PointService;

class Placement
{
    public function __construct(
        private int $x,
        private int $y,
        private ReducedPiece $piece,
        private int $topSideIndex,
        private mixed $context = null
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

    public function getPiece(): ReducedPiece
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
        $pointService = new PointService();

        $topSide = $this->piece->getSide($this->topSideIndex);
        $bottomSide = $this->piece->getSide($this->topSideIndex + 2);

        return ($pointService->getDistanceBetweenPoints($topSide->getStartPoint(), $topSide->getEndPoint()) + $pointService->getDistanceBetweenPoints($bottomSide->getStartPoint(), $bottomSide->getEndPoint())) / 2;
    }

    public function getHeight(): float
    {
        $pointService = new PointService();

        $leftSide = $this->piece->getSide($this->topSideIndex + 1);
        $rightSide = $this->piece->getSide($this->topSideIndex + 3);

        return ($pointService->getDistanceBetweenPoints($leftSide->getStartPoint(), $leftSide->getEndPoint()) + $pointService->getDistanceBetweenPoints($rightSide->getStartPoint(), $rightSide->getEndPoint())) / 2;
    }

    public function getContext(): mixed
    {
        return $this->context;
    }

    public function setContext(mixed $context): Placement
    {
        $this->context = $context;

        return $this;
    }
}
