<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class DerivativePoint extends Point implements JsonSerializable
{
    private bool $extreme = false;

    private bool $usedAsCorner = false;

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

    public function setExtreme(bool $extreme): self
    {
        $this->extreme = $extreme;

        return $this;
    }

    public function isUsedAsCorner(): bool
    {
        return $this->usedAsCorner;
    }

    public function setUsedAsCorner(bool $usedAsCorner): self
    {
        $this->usedAsCorner = $usedAsCorner;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'derivative' => $this->derivative,
                'index' => $this->index,
                'extreme' => $this->extreme,
                'usedAsCorner' => $this->usedAsCorner,
            ]
        );
    }
}
