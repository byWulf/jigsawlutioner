<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Border;
use Bywulf\Jigsawlutioner\Dto\BoundingBox;
use Bywulf\Jigsawlutioner\Dto\PixelMap;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use GdImage;

class BorderFinder
{
    private const DIRECTION_LEFT = 0;
    private const DIRECTION_UP = 3;
    private const DIRECTION_RIGHT = 2;
    private const DIRECTION_DOWN = 1;

    /**
     * @throws BorderParsingException
     */
    public function findPieceBorder(
        GdImage $image,
        float     $threshold = 0.9,
        int     $reduction = 2,
        bool    $returnColorPoints = false,
        bool    $returnTransparentImage = false
    ): Border {
        $objectColor = imagecolorallocate($image, 0, 0, 0);
        $biggestObjectColor = imagecolorallocate($image, 50, 50, 50);
        $backgroundColor = imagecolorallocate($image, 255, 255, 255);
        $surroundingColor = imagecolorallocate($image, 200, 200, 200);

        $pixelMap = PixelMap::createFromImage($image);

        // Make the image black and white to find the borders
        $this->transformImageToMonochrome($pixelMap, $threshold, $objectColor, $backgroundColor);

        // Identify the surrounding area and make it light gray
        $this->fillColorArea($pixelMap, 0, 0, $surroundingColor);

        // Fill everything with black except the surrounding area around the piece
        $this->replaceColor($pixelMap, $backgroundColor, $objectColor);

        // Remove aprox. 2 pixels of the piece border to remove some single pixels
        $this->extendColorArea($pixelMap, $surroundingColor, $reduction, imagecolorallocate($image, 255, 0, 0));

        // Cut every thin lines (black pixels with at least 6 white pixels around it)
        $this->replaceThinPixels($pixelMap, $objectColor, 6, imagecolorallocate($image, 0, 0, 255));

        // Remove every black area, which is not the biggest
        $this->replaceSmallerColorAreas($pixelMap, $objectColor, $biggestObjectColor, imagecolorallocate($image, 0, 255, 0));

        // Check if piece is cut off on an edge
        if ($this->hasBorderPixel($pixelMap, $biggestObjectColor)) {
            throw new BorderParsingException('Piece is cut off');
        }

        list($points, $boundingBox) = $this->getOrderedBorderPoints($pixelMap, $biggestObjectColor);

        // TODO: Simplify points -> https://github.com/byWulf/jigsawlutioner/blob/feaa73dc16249484629ace4900a1440c397a6473/src/pathHelper.js#L186

        return new Border(
            points: $points,
            boundingBox: $boundingBox
        );
    }

    private function transformImageToMonochrome(PixelMap $pixelMap, float $threshold, int $objectColor, int $backgroundColor)
    {
        foreach ($pixelMap->getPixels() as $y => $rows) {
            foreach ($rows as $x => $color) {
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;

                $brightness = (0.2126 * $red + 0.7152 * $green + 0.0722 * $blue);

                $pixelMap->setColor($x, $y, $brightness <= (255 * $threshold) ? $objectColor : $backgroundColor);
            }
        }
    }

    private function fillColorArea(PixelMap $pixelMap, int $x, int $y, int $newColor): void
    {
        $pixelMap->scanFill($x, $y, $newColor);
    }

    private function replaceColor(PixelMap $pixelMap, int $colorToBeReplaced, int $newColor): void
    {
        foreach ($pixelMap->getPixels() as $y => $rows) {
            foreach ($rows as $x => $color) {
                if ($color === $colorToBeReplaced) {
                    $pixelMap->setColor($x, $y, $newColor);
                }
            }
        }
    }

    private function extendColorArea(PixelMap $pixelMap, int $surroundingColor, int $reduction, int $newColor): void
    {
        $surroundingOffsets = [
            ['x' => -1, 'y' => 0],
            ['x' => 1, 'y' => 0],
            ['x' => 0, 'y' => -1],
            ['x' => 0, 'y' => 1],
        ];

        $borderPixels = [];

        foreach ($pixelMap->getPixels() as $y => $row) {
            foreach ($row as $x => $color) {
                if ($color !== $surroundingColor) {
                    continue;
                }

                foreach ($surroundingOffsets as $offset) {
                    if (($pixelMap->getColor($x + $offset['x'], $y + $offset['y']) ?? $surroundingColor) !== $surroundingColor) {
                        $borderPixels[] = ['x' => $x, 'y' => $y];
                    }
                }
            }
        }

        foreach ($borderPixels as $pixel) {
            for ($xOffset = -ceil($reduction / 2); $xOffset <= ceil($reduction / 2); $xOffset++) {
                for ($yOffset = -ceil($reduction / 2); $yOffset <= ceil($reduction / 2); $yOffset++) {
                    if (round(sqrt($xOffset * $xOffset + $yOffset * $yOffset)) > $reduction) {
                        continue;
                    }

                    if (($pixelMap->getColor($pixel['x'] + (int) $xOffset, $pixel['y'] + (int) $yOffset) ?? $surroundingColor) === $surroundingColor) {
                        continue;
                    }

                    $pixelMap->setColor($pixel['x'] + (int) $xOffset, $pixel['y'] + (int) $yOffset, $newColor);
                }
            }
        }
    }

    private function replaceThinPixels(PixelMap $pixelMap, int $objectColor, int $minSurroundingPixels, int $newColor)
    {
        foreach ($pixelMap->getPixels() as $y => $row) {
            foreach ($row as $x => $color) {
                if ($color !== $objectColor) {
                    continue;
                }

                $surroundingOffsets = [
                    ['x' => -1, 'y' => 0],
                    ['x' => 1, 'y' => 0],
                    ['x' => 0, 'y' => -1],
                    ['x' => 0, 'y' => 1],
                    ['x' => -1, 'y' => -1],
                    ['x' => 1, 'y' => -1],
                    ['x' => -1, 'y' => 1],
                    ['x' => 1, 'y' => 1],
                ];

                $positiveSurroundingPixels = 0;
                foreach ($surroundingOffsets as $offset) {
                    if (($pixelMap->getColor($x + $offset['x'], $y + $offset['y']) ?? $objectColor) !== $objectColor) {
                        $positiveSurroundingPixels++;
                    }
                }

                if ($positiveSurroundingPixels >= $minSurroundingPixels) {
                    $pixelMap->setColor($x + $offset['x'], $y + $offset['y'], $newColor);
                }
            }
        }
    }

    private function replaceSmallerColorAreas(PixelMap $pixelMap, int $objectColor, int $biggestObjectColor, int $newColor)
    {
        $areas = [];
        do {
            $areaFound = false;

            foreach ($pixelMap->getPixels() as $y => $rows) {
                foreach ($rows as $x => $color) {
                    if ($color === $objectColor) {
                        $size = $pixelMap->scanFill($x, $y, $biggestObjectColor);
                        $areas[] = ['x' => $x, 'y' => $y, 'size' => $size];

                        $areaFound = true;

                        continue 3;
                    }
                }
            }
        } while ($areaFound);

        // Remove the biggest area
        usort($areas, fn(array $a, array $b): int => $a['size'] <=> $b['size']);
        array_pop($areas);

        foreach ($areas as $area) {
            $pixelMap->scanFill($area['x'], $area['y'], $newColor);
        }
    }

    private function hasBorderPixel(PixelMap $pixelMap, int $objectColor): bool
    {
        $width = $pixelMap->getWidth();
        $height = $pixelMap->getHeight();

        for ($x = 0; $x < $width; $x++) {
            if ($pixelMap->getColor($x, 0) === $objectColor || $pixelMap->getColor($x, $height - 1) === $objectColor) {
                return true;
            }
        }

        for ($y = 0; $y < $height; $y++) {
            if ($pixelMap->getColor(0, $y) === $objectColor || $pixelMap->getColor($width - 1, $y) === $objectColor) {
                return true;
            }
        }

        return false;
    }

    private function getOrderedBorderPoints(PixelMap $pixelMap, int $objectColor): array
    {
        $directionOffsets = [
            self::DIRECTION_LEFT => ['x' => -1, 'y' => 0],
            self::DIRECTION_DOWN => ['x' => 0, 'y' => 1],
            self::DIRECTION_RIGHT => ['x' => 1, 'y' => 0],
            self::DIRECTION_UP => ['x' => 0, 'y' => -1],
        ];

        $points = [];

        foreach ($pixelMap->getPixels() as $y => $row) {
            foreach ($row as $x => $color) {
                if ($color !== $objectColor) {
                    continue;
                }

                $direction = self::DIRECTION_LEFT;
                $firstMovement = null;
                $boundingBox = new BoundingBox($x, $x, $y, $y);

                $points[] = new Point($x, $y);

                while (count($points) < 3 || $x !== $firstMovement['x'] || $y !== $firstMovement['y'] || $direction !== $firstMovement['direction']) {
                    // Start looking one direction back
                    for ($i = $direction + 3; $i < $direction + 7; $i++) {
                        $checkDirection = $i % 4;

                        if (($pixelMap->getColor($x + $directionOffsets[$checkDirection]['x'], $y + $directionOffsets[$checkDirection]['y']) ?? null) === $objectColor) {
                            $x += $directionOffsets[$checkDirection]['x'];
                            $y += $directionOffsets[$checkDirection]['y'];
                            $direction = $checkDirection;

                            $points[] = new Point($x, $y);
                            $boundingBox->setLeft(min($boundingBox->getLeft(), $x));
                            $boundingBox->setRight(max($boundingBox->getRight(), $x));
                            $boundingBox->setTop(min($boundingBox->getTop(), $y));
                            $boundingBox->setBottom(max($boundingBox->getBottom(), $y));

                            break;
                        }
                    }

                    if ($firstMovement === null) {
                        $firstMovement = [
                            'x' => $x,
                            'y' => $y,
                            'direction' => $direction,
                        ];
                    }
                }

                array_pop($points);

                foreach ($points as $point) {
                    $point->setX($point->getX() - $boundingBox->getLeft());
                    $point->setY($point->getY() - $boundingBox->getTop());
                }

                return [$points, $boundingBox];
            }
        }

        throw new BorderParsingException('No area found');
    }
}