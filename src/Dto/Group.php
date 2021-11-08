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
}
