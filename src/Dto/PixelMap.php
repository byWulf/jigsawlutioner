<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\Exception\PixelMapException;
use function count;
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

    /**
     * @throws PixelMapException
     */
    public static function createFromImage(GdImage $image): PixelMap
    {
        $pixels = [];

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $color = imagecolorat($image, $x, $y);
                if ($color === false) {
                    throw new PixelMapException('Could not read pixel color at ' . $x . '/' . $y . '.');
                }

                $pixels[$y][$x] = $color;
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
        $stack = [new Point($x, $y)];

        while (count($stack) > 0) {
            $element = array_pop($stack);
            $x = (int) $element->getX();
            $y = (int) $element->getY();

            $this->pixels[$y][$x] = $color;
            ++$filledPixels;

            $xLeft = $this->fillToLeft($x, $y, $sourceColor, $color, $filledPixels);
            $xRight = $this->fillToRight($x, $y, $sourceColor, $color, $filledPixels);

            $this->expandStack($xLeft, $xRight, $y + 1, $sourceColor, $stack);
            $this->expandStack($xLeft, $xRight, $y - 1, $sourceColor, $stack);
        }

        return $filledPixels;
    }

    private function fillToRight(int $x, int $y, mixed $sourceColor, int $color, int &$filledPixels): int
    {
        ++$x;
        while (isset($this->pixels[$y][$x]) && ($this->pixels[$y][$x] === $sourceColor || $this->pixels[$y][$x] === $color)) {
            $this->pixels[$y][$x] = $color;
            ++$filledPixels;
            ++$x;
        }

        return $x - 1;
    }

    private function fillToLeft(int $x, int $y, int $sourceColor, int $color, int &$filledPixels): int
    {
        --$x;
        while (isset($this->pixels[$y][$x]) && ($this->pixels[$y][$x] === $sourceColor || $this->pixels[$y][$x] === $color)) {
            $this->pixels[$y][$x] = $color;
            ++$filledPixels;
            --$x;
        }

        return $x + 1;
    }

    /**
     * @param Point[] $stack
     */
    private function expandStack(int $xLeft, int $xRight, int $y, int $sourceColor, array &$stack): void
    {
        $x = $xLeft;

        while ($x <= $xRight) {
            $pFlag = false;

            while ($this->isColor($x, $y, $sourceColor) && $x <= $xRight) {
                $pFlag = true;
                ++$x;
            }

            if ($pFlag) {
                $offset = $x === $xRight && $this->isColor($x, $y, $sourceColor) ? 0 : -1;
                $stack[] = new Point($x + $offset, $y);
            }

            do {
                ++$x;
            } while ($this->isNotColor($x, $y, $sourceColor) && $x <= $xRight);
        }
    }

    private function isColor(int $x, int $y, int $color): bool
    {
        return ($this->pixels[$y][$x] ?? null) === $color;
    }

    private function isNotColor(int $x, int $y, int $color): bool
    {
        return ($this->pixels[$y][$x] ?? $color) !== $color;
    }
}
