<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\BorderFinder;

use Bywulf\Jigsawlutioner\Dto\PixelMap;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\PixelMapException;
use GdImage;

class ByWulfBorderFinder implements BorderFinderInterface
{
    private const DIRECTION_LEFT = 0;
    private const DIRECTION_UP = 3;
    private const DIRECTION_RIGHT = 2;
    private const DIRECTION_DOWN = 1;

    public function __construct(
        private float $threshold = 0.95,
        private float $reduction = 0.002
    ) {
    }

    /**
     * @throws BorderParsingException
     *
     * @return Point[]
     */
    public function findPieceBorder(
        GdImage $image,
        ?GdImage $transparentImage
    ): array {
        $objectColor = $this->allocateColor($image, 0, 0, 0);
        $biggestObjectColor = $this->allocateColor($image, 50, 50, 50);
        $backgroundColor = $this->allocateColor($image, 255, 255, 255);
        $surroundingColor = $this->allocateColor($image, 200, 200, 200);

        try {
            $pixelMap = PixelMap::createFromImage($image);
        } catch (PixelMapException $exception) {
            throw new BorderParsingException('Invalid image given.', 0, $exception);
        }

        // Make the image black and white to find the borders
        $this->transformImageToMonochrome($pixelMap, $this->threshold, $objectColor, $backgroundColor);

        // Identify the surrounding area and make it light gray
        $this->fillColorArea($pixelMap, 0, 0, $surroundingColor);

        // Fill everything with black except the surrounding area around the piece
        $this->replaceColor($pixelMap, $backgroundColor, $objectColor);

        // Remove aprox. 2 pixels of the piece border to remove some single pixels
        $this->extendColorArea($pixelMap, $surroundingColor, $this->reduction, $this->allocateColor($image, 170, 170, 170));

        // Cut every thin lines (black pixels with at least 6 white pixels around it)
        $this->replaceThinPixels($pixelMap, $objectColor, $this->allocateColor($image, 140, 140, 200));

        // Remove every black area, which is not the biggest
        $this->replaceSmallerColorAreas($pixelMap, $objectColor, $biggestObjectColor, $this->allocateColor($image, 140, 140, 140));

        // Check if piece is cut off on an edge
        if ($this->hasBorderPixel($pixelMap, $biggestObjectColor)) {
            throw new BorderParsingException('Piece is cut off');
        }

        $points = $this->getOrderedBorderPoints($pixelMap, $biggestObjectColor);

        $pixelMap->applyToImage();

        if ($transparentImage !== null) {
            $this->createTransparentImage($transparentImage, $pixelMap, $biggestObjectColor);
        }

        return $points;
    }

    /**
     * @throws BorderParsingException
     */
    private function allocateColor(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);
        if ($color === false) {
            throw new BorderParsingException('Could not allocate color ' . $red . '/' . $green . '/' . $blue . '.');
        }

        return $color;
    }

    private function transformImageToMonochrome(PixelMap $pixelMap, float $threshold, int $objectColor, int $backgroundColor): void
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

    private function extendColorArea(PixelMap $pixelMap, int $surroundingColor, float $reduction, int $newColor): void
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

        $reductionPerDirection = ceil($pixelMap->getWidth() * $reduction / 2);

        foreach ($borderPixels as $pixel) {
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

    private function replaceThinPixels(PixelMap $pixelMap, int $objectColor, int $newColor): void
    {
        $modifiedPoints = [];
        foreach ($pixelMap->getPixels() as $y => $row) {
            foreach ($row as $x => $color) {
                if ($color !== $objectColor) {
                    continue;
                }

                $radius = $pixelMap->getWidth() * $this->reduction * 3;
                $testPoints = 9;
                $objectPixels = 0;
                $freePixels = 0;
                $mixedPixels = 0;
                for ($rotation = 0; $rotation < 180; $rotation += 180 / $testPoints) {
                    $offsetX = (int) round($radius * cos(deg2rad($rotation)));
                    $offsetY = (int) round($radius * sin(deg2rad($rotation)));

                    $color1 = $pixelMap->getColor($x + $offsetX, $y + $offsetY);
                    $color2 = $pixelMap->getColor($x - $offsetX, $y - $offsetY);

                    if ($color1 === $objectColor && $color2 === $objectColor) {
                        ++$objectPixels;
                    }
                    if (($color1 === $objectColor) !== ($color2 === $objectColor)) {
                        ++$mixedPixels;
                    }
                    if ($color1 !== $objectColor && $color2 !== $objectColor) {
                        ++$freePixels;
                    }

                    if ($objectPixels >= $testPoints * 0.1 && $freePixels >= $testPoints * 0.1) {
                        $pixelMap->setColor($x, $y, $newColor);
                        $modifiedPoints[] = new Point($x, $y);

                        break;
                    }
                    if ($objectPixels >= $testPoints * 0.5 || $freePixels >= $testPoints * 0.5) {
                        break;
                    }
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

    private function replaceSmallerColorAreas(PixelMap $pixelMap, int $objectColor, int $biggestObjectColor, int $newColor): void
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
        usort($areas, fn (array $a, array $b): int => $a['size'] <=> $b['size']);
        array_pop($areas);

        foreach ($areas as $area) {
            $pixelMap->scanFill($area['x'], $area['y'], $newColor);
        }
    }

    private function hasBorderPixel(PixelMap $pixelMap, int $objectColor): bool
    {
        $width = $pixelMap->getWidth();
        $height = $pixelMap->getHeight();

        for ($x = 0; $x < $width; ++$x) {
            if ($pixelMap->getColor($x, 0) === $objectColor || $pixelMap->getColor($x, $height - 1) === $objectColor) {
                return true;
            }
        }

        for ($y = 0; $y < $height; ++$y) {
            if ($pixelMap->getColor(0, $y) === $objectColor || $pixelMap->getColor($width - 1, $y) === $objectColor) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws BorderParsingException
     *
     * @return Point[]
     */
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

                $points[] = new Point($x, $y);

                while (count($points) < 3 || $firstMovement === null || $x !== $firstMovement['x'] || $y !== $firstMovement['y'] || $direction !== $firstMovement['direction']) {
                    // Start looking one direction back
                    for ($i = $direction + 3; $i < $direction + 7; ++$i) {
                        $checkDirection = $i % 4;

                        if (($pixelMap->getColor($x + $directionOffsets[$checkDirection]['x'], $y + $directionOffsets[$checkDirection]['y']) ?? null) === $objectColor) {
                            $x += $directionOffsets[$checkDirection]['x'];
                            $y += $directionOffsets[$checkDirection]['y'];
                            $direction = $checkDirection;

                            $points[] = new Point($x, $y);

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

                // Remove the last two points to not duplicate them
                array_pop($points);
                array_pop($points);

                return $points;
            }
        }

        throw new BorderParsingException('No area found');
    }

    private function createTransparentImage(GdImage $transparentImage, PixelMap $pixelMap, int $opaqueColor): void
    {
        $transparentColor = imagecolorallocatealpha($transparentImage, 255, 255, 255, 0);
        if ($transparentColor === false) {
            throw new BorderParsingException('Color could not be created.');
        }

        for ($y = 0; $y < imagesy($transparentImage); $y++) {
            for ($x = 0; $x < imagesx($transparentImage); $x++) {
                if ($pixelMap->getColor($x, $y) !== $opaqueColor) {
                    imagesetpixel($transparentImage, $x, $y, $transparentColor);
                }
            }
        }
    }
}
