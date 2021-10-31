<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class Side implements JsonSerializable
{
    /**
     * @param Point[] $points
     */
    public function __construct(
        private array $points
    ) {
    }

    /**
     * @return Point[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    public function jsonSerialize(): array
    {
        return [
            'points' => array_map(
                fn (Point $point): array => $point->jsonSerialize(),
                $this->points
            ),
        ];
    }
}
