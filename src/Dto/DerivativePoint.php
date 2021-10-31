<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class DerivativePoint extends Point implements JsonSerializable
{
    private bool $extreme = false;

    public function __construct(
        float $x,
        float $y,
        private float $derivative,
        private int $index
    ) {
        parent::__construct($x, $y);
    }

    public function getDerivative(): float
    {
        return $this->derivative;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function isExtreme(): bool
    {
        return $this->extreme;
    }

    public function setExtreme(bool $extreme): void
    {
        $this->extreme = $extreme;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'derivative' => $this->derivative,
                'index' => $this->index,
                'extreme' => $this->extreme,
            ]
        );
    }
}
