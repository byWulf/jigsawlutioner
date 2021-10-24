<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use GdImage;

class PixelMap
{
    /**
     * @param int[][] $pixels
     */
    private function __construct(
        private GdImage $image,
        private array $pixels,
        private int $width,
        private int $height
    ) {
    }

    public static function createFromImage(GdImage $image): PixelMap
    {
        $pixels = [];

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixels[$y][$x] = imagecolorat($image, $x, $y);
            }
        }

        return new PixelMap($image, $pixels, $width, $height);
    }

    public function applyToImage(): void
    {
        foreach ($this->pixels as $y => $row) {
            foreach ($row as $x => $color) {
                imagesetpixel($this->image, $x, $y, $color);
            }
        }
    }

    public function getImage(): GdImage
    {
        return $this->image;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getColor(int $x, int $y): ?int
    {
        return $this->pixels[$y][$x] ?? null;
    }

    /**
     * @return int[][]
     */
    public function getPixels(): array
    {
        return $this->pixels;
    }

    public function setColor(int $x, int $y, int $color): void
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