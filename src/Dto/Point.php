<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Point
{
    public function __construct(
        private int $x,
        private int $y
    ) {
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function setX(int $x): void
    {
        $this->x = $x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function setY(int $y): void
    {
        $this->y = $y;
    }
}