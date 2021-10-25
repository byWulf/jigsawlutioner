<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Border;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;

class SideFinder
{
    public function __construct(private MathService $mathService)
    {
    }

    public function getSides(Border $border): array
    {
        // We get all derivative rotations aka how much the rotation changed on each point
        $derivatives = $this->getDerivativeRotations($border->getPoints());

        // We only want to look at the lowest derivations because a corner would be -90°
        $derivatives = array_filter($derivatives, fn(int $derivative): bool => $derivative < -25);
        asort($derivatives);
        $derivatives = array_slice($derivatives, 0, 20, true);

        array_walk($derivatives, fn (float &$value, int $key) => $value = ['point' => $border->getPoints()[$key], 'derivative' => $value, 'index' => $key]);
        $count = count($derivatives);

        $derivatives = array_values($derivatives);

        for ($i1 = 0; $i1 < $count; $i1++) {
            for ($i2 = $i1 + 1; $i2 < $count; $i2++) {
                for ($i3 = $i2 + 1; $i3 < $count; $i3++) {
                    for ($i4 = $i3 + 1; $i4 < $count; $i4++) {

                        $activeDerivatives = [
                            $derivatives[$i1],
                            $derivatives[$i2],
                            $derivatives[$i3],
                            $derivatives[$i4],
                        ];
                        usort($activeDerivatives, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

                        // 1. Check that opposite sides are equally long
                        $distance0 = $this->mathService->getDistanceBetweenPoints($activeDerivatives[0]['point'], $activeDerivatives[1]['point']);
                        $distance2 = $this->mathService->getDistanceBetweenPoints($activeDerivatives[2]['point'], $activeDerivatives[3]['point']);
                        if (abs($distance0 - $distance2) > 0.2 * min($distance0, $distance2)) {
                            continue;
                        }

                        $distance1 = $this->mathService->getDistanceBetweenPoints($activeDerivatives[1]['point'], $activeDerivatives[2]['point']);
                        $distance3 = $this->mathService->getDistanceBetweenPoints($activeDerivatives[3]['point'], $activeDerivatives[0]['point']);
                        if (abs($distance1 - $distance3) > 0.2 * min($distance1, $distance3)) {
                            continue;
                        }

                        // 2. Check that opposite sides are equally rotated
                        $rotation0 = $this->mathService->getRotation($activeDerivatives[0]['point'], $activeDerivatives[1]['point']);
                        $rotation2 = $this->mathService->getRotation($activeDerivatives[3]['point'], $activeDerivatives[2]['point']);
                        $rotationDiff0 = abs($this->mathService->normalizeRotation($rotation0 - $rotation2));
                        if ($rotationDiff0 > 10) {
                            continue;
                        }

                        $rotation1 = $this->mathService->getRotation($activeDerivatives[1]['point'], $activeDerivatives[2]['point']);
                        $rotation3 = $this->mathService->getRotation($activeDerivatives[0]['point'], $activeDerivatives[3]['point']);
                        $rotationDiff1 = abs($this->mathService->normalizeRotation($rotation1 - $rotation3));
                        if ($rotationDiff1 > 10) {
                            continue;
                        }

                        // 4. Check that the sides are in their length not too far away from another
                        if (abs(min($distance0, $distance2) - min($distance1, $distance3)) > 0.5 * min($distance0, $distance1, $distance2, $distance3)) {
                            continue;
                        }

                        // 5. Check that the first 10% are straight to the side
                        // TODO

                        return [
                            array_slice($border->getPoints(), $activeDerivatives[0]['index'], $activeDerivatives[1]['index'] - $activeDerivatives[0]['index'] + 1),
                            array_slice($border->getPoints(), $activeDerivatives[1]['index'], $activeDerivatives[2]['index'] - $activeDerivatives[1]['index'] + 1),
                            array_slice($border->getPoints(), $activeDerivatives[2]['index'], $activeDerivatives[3]['index'] - $activeDerivatives[2]['index'] + 1),
                            array_merge(
                                array_slice($border->getPoints(), $activeDerivatives[3]['index'], count($border->getPoints()) - $activeDerivatives[3]['index']),
                                array_slice($border->getPoints(), 0, $activeDerivatives[0]['index'] + 1)
                            )
                        ];
                    }
                }
            }
        }

        throw new SideParsingException('Not recognized as piece.');
    }

    /**
     * @param Point[] $points
     * @return int[]
     */
    private function getDerivativeRotations(array $points): array
    {
        $derivations = [];
        $length = 0;
        foreach ($points as $index => $point) {
            $pointBefore = $points[$index - 1] ?? $points[count($points) - 1];
            $pointAfter = $points[$index + 1] ?? $points[0];

            $rotationBefore = $this->mathService->getRotation($pointBefore, $point);
            $rotationAfter = $this->mathService->getRotation($point, $pointAfter);

            $derivations[] = $this->mathService->normalizeRotation($rotationAfter - $rotationBefore);

            $length += $this->mathService->getDistanceBetweenPoints($point, $pointAfter);
        }

        return $derivations;
    }

    private function countPointsInRadius(array $points, Point $point, float $radius): int
    {
        $points = array_filter($points, fn(Point $checkPoint): bool => $this->mathService->getDistanceBetweenPoints($checkPoint, $point) <= $radius);

        return count($points);
    }
}