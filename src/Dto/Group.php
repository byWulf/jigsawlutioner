<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use LogicException;
use Stringable;

class Group implements Stringable
{
    private static $indexCounter = 0;

    private int $index;

    public function __construct()
    {
        $this->index = self::$indexCounter++;
    }

    /**
     * @var Placement[]
     */
    private array $placements = [];

    /**
     * @var Placement[][][]
     */
    private array $placementsByPosition = [];

    /**
     * @var Placement[]
     */
    private array $placementsByPiece = [];

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getFirstPlacement(): ?Placement
    {
        $firstPlacement = reset($this->placements);

        return $firstPlacement ?: null;
    }

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
        $this->placementsByPosition[$placement->getY()][$placement->getX()][] = $placement;
        $this->placementsByPiece[$placement->getPiece()->getIndex()] = $placement;

        return $this;
    }

    public function removePlacement(Placement $placement, bool $refreshCache = true): self
    {
        $index = array_search($placement, $this->placements, true);
        if ($index !== false) {
            unset($this->placements[$index]);

            if ($refreshCache) {
                $this->updateIndexedPlacements();
            }
        }

        return $this;
    }

    public function getPlacementByPiece(Piece $piece): ?Placement
    {
        return $this->placementsByPiece[$piece->getIndex()] ?? null;
    }

    public function getFirstPlacementByPosition(int $x, int $y): ?Placement
    {
        if (!isset($this->placementsByPosition[$y][$x])) {
            return null;
        }

        return reset($this->placementsByPosition[$y][$x]) ?: null;
    }

    public function getLastPlacementByPosition(int $x, int $y): ?Placement
    {
        if (!isset($this->placementsByPosition[$y][$x])) {
            return null;
        }

        return end($this->placementsByPosition[$y][$x]) ?: null;
    }

    public function getPlacementByPosition(int $x, int $y): ?Placement
    {
        if (count($this->placementsByPosition[$y][$x] ?? []) > 1) {
            throw new LogicException('More than one piece given on the requested position.');
        }

        return $this->getFirstPlacementByPosition($x, $y);
    }

    public function getPlacementsGroupedByPosition(): array
    {
        return $this->placementsByPosition;
    }

    public function getPlacementsByPosition(int $x, int $y): array
    {
        return $this->placementsByPosition[$y][$x] ?? [];
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

        $this->updateIndexedPlacements();

        return $this;
    }

    public function move(int $xOffset, int $yOffset): self
    {
        foreach ($this->placements as $placement) {
            $placement->setX($placement->getX() + $xOffset);
            $placement->setY($placement->getY() + $yOffset);
        }

        $this->updateIndexedPlacements();

        return $this;
    }

    public function __clone(): void
    {
        foreach ($this->placements as $index => $placement) {
            $this->placements[$index] = clone $placement;
        }

        $this->updateIndexedPlacements();
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

    public function updateIndexedPlacements(): void
    {
        $this->placementsByPosition = [];
        $this->placementsByPiece = [];
        foreach ($this->placements as $placement) {
            $this->placementsByPosition[$placement->getY()][$placement->getX()][] = $placement;
            $this->placementsByPiece[$placement->getPiece()->getIndex()] = $placement;
        }
    }

    public function __toString(): string
    {
        return 'group #' . $this->index;
    }
}
