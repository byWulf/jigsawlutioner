<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use GdImage;

class PixelMap
{
    /**
     * @param int[][] $pixels
     */
    public function __construct(
        private GdImage $image,
        private array $pixels
    ) {
    }

    public function getImage(): GdImage
    {
        return $this->image;
    }

    public function getWidth(): int
    {
        return imagesx($this->image);
    }

    public function getHeight(): int
    {
        return imagesy($this->image);
    }

    /**
     * @return int[][]
     */
    public function getPixels(): array
    {
        return $this->pixels;
    }

    public function setPixel(int $x, int $y, int $color): void
    {
        if (!isset($this->pixels[$y][$x])) {
            return;
        }

        $this->pixels[$y][$x] = $color;
    }

    public function scanFill(int $x, int $y, int $color): int
    {
        $sourceColor = $this->pixels[$y][$x];

        $filledPixels = 0;
        $stack[] = ['x' => $x, 'y' => $y];
        while (count($stack) > 0) {
            $element = array_pop($stack);
            $x = $element['x'];
            $y = $element['y'];

            $this->pixels[$y][$x] = $color;
            $filledPixels++;

            $saveX = $x;

            $x--;
            while (isset($this->pixels[$y][$x]) && ($this->pixels[$y][$x] === $sourceColor || $this->pixels[$y][$x] === $color)) {
                $this->pixels[$y][$x] = $color;
                $filledPixels++;
                $x--;
            }
            $xLeft = $x + 1;

            $x = $saveX + 1;
            while (isset($this->pixels[$y][$x]) && ($this->pixels[$y][$x] === $sourceColor || $this->pixels[$y][$x] === $color)) {
                $this->pixels[$y][$x] = $color;
                $filledPixels++;
                $x++;
            }
            $xRight = $x - 1;

            $currentY = $y;
            foreach ([-1, 1] as $offset) {
                $y = $currentY + $offset;
                $x = $xLeft;

                while ($x <= $xRight) {
                    $pFlag = false;

                    while (($this->pixels[$y][$x] ?? null) === $sourceColor && $x <= $xRight) {
                        $pFlag = true;
                        $x++;
                    }

                    if ($pFlag) {
                        if ($x === $xRight && ($this->pixels[$y][$x] ?? null) === $sourceColor) {
                            $stack[] = ['x' => $x, 'y' => $y];
                        } else {
                            $stack[] = ['x' => $x - 1, 'y' => $y];
                        }
                    }

                    do {
                        $x++;
                    } while (isset($this->pixels[$y][$x]) && $this->pixels[$y][$x] !== $sourceColor && $x <= $xRight);
                }
            }
        }

        return $filledPixels;
    }
}