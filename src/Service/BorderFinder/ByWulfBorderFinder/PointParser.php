<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;

use Bywulf\Jigsawlutioner\Dto\PixelMap;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsing\NoAreaFoundException;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;

class PointParser
{
    /**
     * @throws BorderParsingException
     *
     * @return Point[]
     */
    public function getOrderedBorderPoints(PixelMap $pixelMap, int $objectColor): array
    {
        foreach ($pixelMap->getPixels() as $y => $row) {
            foreach ($row as $x => $color) {
                if ($color !== $objectColor) {
                    continue;
                }

                $points = $this->getConnectedPointsFromPoint($x, $y, $pixelMap, $objectColor);

                // Remove the last two points to not duplicate them
                array_pop($points);
                array_pop($points);

                return $points;
            }
        }

        throw new NoAreaFoundException();
    }

    /**
     * @return Point[]
     */
    private function getConnectedPointsFromPoint(int $x, int $y, PixelMap $pixelMap, int $objectColor): array
    {
        $direction = ByWulfBorderFinder::DIRECTION_LEFT;

        $firstMovement = null;

        $points = [];
        $points[] = new Point($x, $y);

        $hasEnoughPoints = false;
        while (!$hasEnoughPoints || $firstMovement === null || $x !== $firstMovement['x'] || $y !== $firstMovement['y'] || $direction !== $firstMovement['direction']) {
            $points[] = $this->getNextPoint($pixelMap, $direction, $x, $y, $objectColor);
            if (!$hasEnoughPoints && count($points) >= 3) {
                $hasEnoughPoints = true;
            }

            if ($firstMovement === null) {
                $firstMovement = [
                    'x' => $x,
                    'y' => $y,
                    'direction' => $direction,
                ];
            }
        }

        return $points;
    }

    private function getNextPoint(PixelMap $pixelMap, int &$direction, int &$x, int &$y, int $objectColor): Point
    {
        $directionOffsets = [
            ByWulfBorderFinder::DIRECTION_LEFT => ['x' => -1, 'y' => 0],
            ByWulfBorderFinder::DIRECTION_DOWN => ['x' => 0, 'y' => 1],
            ByWulfBorderFinder::DIRECTION_RIGHT => ['x' => 1, 'y' => 0],
            ByWulfBorderFinder::DIRECTION_UP => ['x' => 0, 'y' => -1],
        ];

        // Start looking one direction back
        for ($i = $direction + 3; $i < $direction + 7; ++$i) {
            $checkDirection = $i % 4;

            if ($pixelMap->getColor($x + $directionOffsets[$checkDirection]['x'], $y + $directionOffsets[$checkDirection]['y']) !== $objectColor) {
                continue;
            }

            $x += $directionOffsets[$checkDirection]['x'];
            $y += $directionOffsets[$checkDirection]['y'];
            $direction = $checkDirection;

            return new Point($x, $y);
        }

        throw new BorderParsingException('No connecting point found.');
    }
}
