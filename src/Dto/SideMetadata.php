<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class SideMetadata
{
    /**
     * @param array<int, float> $pointWidths
     */
    public function __construct(
        private Side $side,
        private float $sideWidth,
        private float $depth,
        private int $deepestIndex,
        private array $pointWidths
    ) {
    }

    public function getSide(): Side
    {
        return $this->side;
    }

    public function getSideWidth(): float
    {
        return $this->sideWidth;
    }

    public function getDepth(): float
    {
        return $this->depth;
    }

    public function getDeepestIndex(): int
    {
        return $this->deepestIndex;
    }

    /**
     * @return array<int, float>
     */
    public function getPointWidths(): array
    {
        return $this->pointWidths;
    }
}
