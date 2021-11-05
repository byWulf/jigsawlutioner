<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class Piece implements JsonSerializable
{
    /**
     * @param DerivativePoint[] $borderPoints
     * @param Side[]            $sides
     */
    public function __construct(
        private array $borderPoints,
        private array $sides
    ) {
    }

    /**
     * @return DerivativePoint[]
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
                fn (DerivativePoint $point): array => $point->jsonSerialize(),
                $this->borderPoints
            ),
            'sides' => array_map(
                fn (Side $side): array => $side->jsonSerialize(),
                $this->sides
            ),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new Piece(
            array_map(fn (array $pointData): DerivativePoint => DerivativePoint::fromArray($pointData), $data['borderPoints']),
            array_map(fn (array $sideData): Side => Side::fromArray($sideData), $data['sides']),
        );
    }
}
