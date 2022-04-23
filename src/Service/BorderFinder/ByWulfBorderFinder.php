<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\BorderFinder;

use Bywulf\Jigsawlutioner\Dto\Context\BorderFinderContextInterface;
use Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext;
use Bywulf\Jigsawlutioner\Dto\PixelMap;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\PixelMapException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder\PixelManipulator;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder\PointParser;
use GdImage;
use InvalidArgumentException;

class ByWulfBorderFinder implements BorderFinderInterface
{
    public const DIRECTION_LEFT = 0;

    public const DIRECTION_UP = 3;

    public const DIRECTION_RIGHT = 2;

    public const DIRECTION_DOWN = 1;

    private PointParser $pointParser;

    private PixelManipulator $pixelManipulator;

    public function __construct(
        private float $reduction = 0.002
    ) {
        $this->pointParser = new PointParser();
        $this->pixelManipulator = new PixelManipulator();
    }

    /**
     * @throws BorderParsingException
     *
     * @return Point[]
     */
    public function findPieceBorder(
        GdImage $image,
        BorderFinderContextInterface $context
    ): array {
        if (!$context instanceof ByWulfBorderFinderContext) {
            throw new InvalidArgumentException('Expected context of type ' . ByWulfBorderFinderContext::class . ', got ' . $context::class);
        }

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
        $this->transformImageToMonochrome($pixelMap, $context->getThreshold(), $objectColor, $backgroundColor);

        // Identify the surrounding area and make it light gray
        $this->pixelManipulator->fillColorArea($pixelMap, 0, 0, $backgroundColor, $surroundingColor);
        $this->pixelManipulator->fillColorArea($pixelMap, 0, $pixelMap->getHeight() - 1, $backgroundColor, $surroundingColor);
        $this->pixelManipulator->fillColorArea($pixelMap, $pixelMap->getWidth() - 1, $pixelMap->getHeight() - 1, $backgroundColor, $surroundingColor);
        $this->pixelManipulator->fillColorArea($pixelMap, $pixelMap->getWidth() - 1, 0, $backgroundColor, $surroundingColor);

        // Fill everything with black except the surrounding area around the piece
        $this->pixelManipulator->replaceColor($pixelMap, $backgroundColor, $objectColor);

        // Remove aprox. 2 pixels of the piece border to remove some single pixels
        $this->pixelManipulator->extendColorArea($pixelMap, $surroundingColor, $this->reduction, $this->allocateColor($image, 170, 170, 170));

        // Cut every thin lines (black pixels with at least 6 white pixels around it)
        $this->pixelManipulator->replaceThinPixels($pixelMap, $objectColor, $this->allocateColor($image, 140, 140, 200), $pixelMap->getWidth() * $this->reduction * 3);

        // Remove every black area, which is not the biggest
        $this->pixelManipulator->replaceSmallerColorAreas($pixelMap, $objectColor, $biggestObjectColor, $this->allocateColor($image, 140, 140, 140));

        // Check if piece is cut off on an edge
        if ($this->hasBorderPixel($pixelMap, $biggestObjectColor)) {
            throw new BorderParsingException('Piece is cut off');
        }

        // Make given images transparent if wished
        foreach ($context->getTransparentImages() as $transparentImage) {
            $this->transparencifyImage($transparentImage, $pixelMap, $biggestObjectColor);
        }

        return $this->pointParser->getOrderedBorderPoints($pixelMap, $biggestObjectColor);
    }

    /**
     * @throws BorderParsingException
     */
    private function allocateColor(GdImage $image, int $red, int $green, int $blue, ?int $alpha = null): int
    {
        if ($alpha !== null) {
            $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
        } else {
            $color = imagecolorallocate($image, $red, $green, $blue);
        }

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
     */
    private function transparencifyImage(GdImage $transparentImage, PixelMap $pixelMap, int $opaqueColor): void
    {
        $transparentColor = $this->allocateColor($transparentImage, 255, 255, 255, 0);

        imagecolortransparent($transparentImage, $transparentColor);

        $xFactor = $pixelMap->getWidth() / imagesx($transparentImage);
        $yFactor = $pixelMap->getHeight() / imagesy($transparentImage);

        for ($y = 0; $y < imagesy($transparentImage); ++$y) {
            for ($x = 0; $x < imagesx($transparentImage); ++$x) {
                if ($pixelMap->getColor((int) ($x * $xFactor), (int) ($y * $yFactor)) !== $opaqueColor) {
                    imagesetpixel($transparentImage, $x, $y, $transparentColor);
                }
            }
        }
    }
}
