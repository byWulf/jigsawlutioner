<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class ReducedSide implements JsonSerializable
{
    public function __construct(
        private readonly int $direction,
        private readonly Point $startPoint,
        private readonly Point $endPoint
    ) {
    }

    public static function fromSide(Side $side): self
    {
        return new self($side->getDirection(), $side->getStartPoint(), $side->getEndPoint());
    }

    public function getDirection(): int
    {
        return $this->direction;
    }

    public function getStartPoint(): Point
    {
        return $this->startPoint;
    }

    public function getEndPoint(): Point
    {
        return $this->endPoint;
    }

    public function jsonSerialize(): array
    {
        return [
            'direction' => $this->direction,
        ];
    }
}
