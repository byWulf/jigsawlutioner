<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Border
{
    public function __construct(
        private array $points,
        private BoundingBox $boundingBox
    ) {
    }

    public function getPoints(): array
    {
        return $this->points;
    }

    public function getBoundingBox(): BoundingBox
    {
        return $this->boundingBox;
    }
}