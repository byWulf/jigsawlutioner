<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use InvalidArgumentException;

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

    public function removePlacement(Placement $placement): self
    {
        $index = array_search($placement, $this->placements, true);
        if ($index !== false) {
            unset($this->placements[$index]);
        }

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

    public function getFirstPlacementByPosition(int $x, int $y): ?Placement
    {
        foreach ($this->placements as $placement) {
            if ($placement->getX() === $x && $placement->getY() === $y) {
                return $placement;
            }
        }

        return null;
    }

    public function getPlacementsByPosition(int $x, int $y): array
    {
        return array_filter($this->placements, fn (Placement $placement): bool => $placement->getX() === $x && $placement->getY() === $y);
    }

    public function rotate(int $clockwiseRotations): self
    {
        while ($clockwiseRotations < 0) {
            $clockwiseRotations += 4;
        }

        for ($i = 0; $i < $clockwiseRotations; ++$i) {
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

    public function getWidth(): int
    {
        if (count($this->placements) === 0) {
            return 0;
        }
        return
            max(array_map(fn (Placement $placement): int => $placement->getX(), $this->placements)) -
            min(array_map(fn (Placement $placement): int => $placement->getX(), $this->placements)) +
            1
        ;
    }

    public function getHeight(): int
    {
        if (count($this->placements) === 0) {
            return 0;
        }

        return
            max(array_map(fn (Placement $placement): int => $placement->getY(), $this->placements)) -
            min(array_map(fn (Placement $placement): int => $placement->getY(), $this->placements)) +
            1
        ;
    }

    public function hasOpenSide(Placement $placement): bool
    {
        if (
            $this->getFirstPlacementByPosition($placement->getX(), $placement->getY() - 1) === null &&
            $placement->getPiece()->getSide($placement->getTopSideIndex())->getDirection() !== DirectionClassifier::NOP_STRAIGHT
        ) {
            return true;
        }

        if (
            $this->getFirstPlacementByPosition($placement->getX() - 1, $placement->getY()) === null &&
            $placement->getPiece()->getSide($placement->getTopSideIndex() + 1)->getDirection() !== DirectionClassifier::NOP_STRAIGHT
        ) {
            return true;
        }

        if (
            $this->getFirstPlacementByPosition($placement->getX(), $placement->getY() + 1) === null &&
            $placement->getPiece()->getSide($placement->getTopSideIndex() + 2)->getDirection() !== DirectionClassifier::NOP_STRAIGHT
        ) {
            return true;
        }

        if (
            $this->getFirstPlacementByPosition($placement->getX() + 1, $placement->getY()) === null &&
            $placement->getPiece()->getSide($placement->getTopSideIndex() + 3)->getDirection() !== DirectionClassifier::NOP_STRAIGHT
        ) {
            return true;
        }

        return false;
    }
}
