<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;

use Bywulf\Jigsawlutioner\Dto\PixelMap;
use Bywulf\Jigsawlutioner\Dto\Point;

class PixelManipulator
{
    public function fillColorArea(PixelMap $pixelMap, int $x, int $y, int $oldColor, int $newColor): void
    {
        if ($pixelMap->getColor($x, $y) === $oldColor) {
            $pixelMap->scanFill($x, $y, $newColor);
        }
    }

    public function replaceColor(PixelMap $pixelMap, int $colorToBeReplaced, int $newColor): void
    {
        foreach ($pixelMap->getPixels() as $y => $rows) {
            foreach ($rows as $x => $color) {
                if ($color === $colorToBeReplaced) {
                    $pixelMap->setColor($x, $y, $newColor);
                }
            }
        }
    }

    public function extendColorArea(PixelMap $pixelMap, int $surroundingColor, float $reduction, int $newColor): void
    {
        $reductionPerDirection = ceil($pixelMap->getWidth() * $reduction / 2);

        foreach ($this->getBorderPixels($pixelMap, $surroundingColor) as $pixel) {
            for ($xOffset = -$reductionPerDirection; $xOffset <= $reductionPerDirection; ++$xOffset) {
                for ($yOffset = -$reductionPerDirection; $yOffset <= $reductionPerDirection; ++$yOffset) {
                    if (round(sqrt($xOffset * $xOffset + $yOffset * $yOffset)) > $reductionPerDirection * 2) {
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

    /**
     * @return array<array{x: int, y: int}>
     */
    private function getBorderPixels(PixelMap $pixelMap, int $surroundingColor): array
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

        return $borderPixels;
    }

    public function replaceThinPixels(PixelMap $pixelMap, int $objectColor, int $newColor, float $radius): void
    {
        $modifiedPoints = [];
        foreach ($pixelMap->getPixels() as $y => $row) {
            foreach ($row as $x => $color) {
                if ($color !== $objectColor) {
                    continue;
                }

                if ($this->isThinPixel($pixelMap, $x, $y, $objectColor, $radius)) {
                    $pixelMap->setColor($x, $y, $newColor);
                    $modifiedPoints[] = new Point($x, $y);
                }
            }
        }

        foreach ($modifiedPoints as $point) {
            $objectPixels = 0;
            foreach ([new Point(-1, 0), new Point(1, 0), new Point(0, -1), new Point(0, 1)] as $offset) {
                if ($pixelMap->getColor((int) ($point->getX() + $offset->getX()), (int) ($point->getY() + $offset->getY())) === $objectColor) {
                    ++$objectPixels;
                }

                if ($objectPixels >= 3) {
                    $pixelMap->setColor((int) $point->getX(), (int) $point->getY(), $objectColor);

                    break;
                }
            }
        }
    }

    private function isThinPixel(PixelMap $pixelMap, int $x, int $y, int $objectColor, float $radius): bool
    {
        $testPoints = 9;
        $objectPixels = 0;
        $freePixels = 0;
        for ($rotation = 0; $rotation < 180; $rotation += 180 / $testPoints) {
            $offsetX = (int) round($radius * cos(deg2rad($rotation)));
            $offsetY = (int) round($radius * sin(deg2rad($rotation)));

            $color1 = $pixelMap->getColor($x + $offsetX, $y + $offsetY);
            $color2 = $pixelMap->getColor($x - $offsetX, $y - $offsetY);

            if ($color1 === $objectColor && $color2 === $objectColor) {
                ++$objectPixels;
            }
            if ($color1 !== $objectColor && $color2 !== $objectColor) {
                ++$freePixels;
            }

            if ($objectPixels >= $testPoints * 0.1 && $freePixels >= $testPoints * 0.1) {
                return true;
            }
            if (max($objectPixels, $freePixels) >= $testPoints * 0.5) {
                break;
            }
        }

        return false;
    }

    public function replaceSmallerColorAreas(PixelMap $pixelMap, int $objectColor, int $biggestObjectColor, int $newColor): void
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
        usort($areas, static fn (array $a, array $b): int => $a['size'] <=> $b['size']);
        array_pop($areas);

        foreach ($areas as $area) {
            $pixelMap->scanFill($area['x'], $area['y'], $newColor);
        }
    }
}
