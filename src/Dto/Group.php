<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Group
{
    /**
     * @var Placement[]
     */
    private array $placements = [];

    /**
     * @return Placement[]
     */
    public function getPlacements(): array
    {
        return $this->placements;
    }

    public function addPlacement(Placement $placement): self
    {
        $this->placements[] = $placement;

        return $this;
    }

    public function getPlacementByPiece(Piece $piece): ?Placement
    {
        foreach ($this->placements as $placement) {
            if ($placement->getPiece() === $piece) {
                return $placement;
            }
        }

        return null;
    }

    public function getPlacementByPosition(int $x, int $y): ?Placement
    {
        foreach ($this->placements as $placement) {
            if ($placement->getX() === $x && $placement->getY() === $y) {
                return $placement;
            }
        }

        return null;
    }

    public function rotate(int $clockwiseRotations): self
    {
        while ($clockwiseRotations < 0) {
            $clockwiseRotations += 4;
        }

        for ($i = 0; $i < $clockwiseRotations; $i++) {
            foreach ($this->placements as $placement) {
                $placement->setTopSideIndex(($placement->getTopSideIndex() + 1) % 4);
                $oldX = $placement->getX();
                $placement->setX(-$placement->getY());
                $placement->setY($oldX);
            }
        }

        return $this;
    }

    public function move(int $xOffset, int $yOffset): self
    {
        foreach ($this->placements as $placement) {
            $placement->setX($placement->getX() + $xOffset);
            $placement->setY($placement->getY() + $yOffset);
        }

        return $this;
    }

    public function __clone(): void
    {
        foreach ($this->placements as $index => $placement) {
            $this->placements[$index] = clone $placement;
        }
    }
}
