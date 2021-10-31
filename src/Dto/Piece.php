<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class Piece implements JsonSerializable
{
    /**
     * @param Point[] $borderPoints
     * @param Side[]  $sides
     */
    public function __construct(
        private array $borderPoints,
        private array $sides
    ) {
    }

    /**
     * @return Point[]
     */
    public function getBorderPoints(): array
    {
        return $this->borderPoints;
    }

    /**
     * @return Side[]
     */
    public function getSides(): array
    {
        return $this->sides;
    }

    public function jsonSerialize(): array
    {
        return [
            'borderPoints' => array_map(
                fn (Point $point): array => $point->jsonSerialize(),
                $this->borderPoints
            ),
            'sides' => array_map(
                fn (Side $side): array => $side->jsonSerialize(),
                $this->sides
            ),
        ];
    }
}
