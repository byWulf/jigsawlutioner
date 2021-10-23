<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class BoundingBox
{
    public function __construct(
        private int $left,
        private int $right,
        private int $top,
        private int $bottom
    ) {

    }

    public function getLeft(): int
    {
        return $this->left;
    }

    public function setLeft(int $left): void
    {
        $this->left = $left;
    }

    public function getRight(): int
    {
        return $this->right;
    }

    public function setRight(int $right): void
    {
        $this->right = $right;
    }

    public function getTop(): int
    {
        return $this->top;
    }

    public function setTop(int $top): void
    {
        $this->top = $top;
    }

    public function getBottom(): int
    {
        return $this->bottom;
    }

    public function setBottom(int $bottom): void
    {
        $this->bottom = $bottom;
    }
}