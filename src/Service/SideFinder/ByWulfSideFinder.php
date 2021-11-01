<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideFinder;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\PathService;
use Bywulf\Jigsawlutioner\Service\PointService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class ByWulfSideFinder implements SideFinderInterface, LoggerAwareInterface
{
    private const DERIVATIVE_LIMIT = -40;
    private const DERIVATIVE_NEIGHBOUR_LOOKAHEAD = 20;
    private ?LoggerInterface $logger = null;
    private PointService $pointService;
    private PathService $pathService;

    public function __construct(
        private float $derivationLookahead = 25
    ) {
        $this->pointService = new PointService();
        $this->pathService = new PathService();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param Point[] $borderPoints
     *
     * @return Side[]
     */
    public function getSides(array &$borderPoints): array
    {
        // We get all derivative rotations aka how much the rotation changed on each point
        $borderPoints = $this->getDerivativePoints($borderPoints);

        foreach ($this->iterateOverBestDerivativePoints($borderPoints) as $activeDerivatives) {
            $this->logger?->debug('Looking at the following points:', $activeDerivatives);

            // 1. Check that opposite sides are equally long
            $distance0 = $this->pointService->getDistanceBetweenPoints($activeDerivatives[0], $activeDerivatives[1]);
            $distance2 = $this->pointService->getDistanceBetweenPoints($activeDerivatives[2], $activeDerivatives[3]);
            if (abs($distance0 - $distance2) > 0.4 * min($distance0, $distance2)) {
                $this->logger?->debug(' -> Distance of sides 0 and 2 more than 40% apart', [$distance0, $distance2]);

                continue;
            }

            $distance1 = $this->pointService->getDistanceBetweenPoints($activeDerivatives[1], $activeDerivatives[2]);
            $distance3 = $this->pointService->getDistanceBetweenPoints($activeDerivatives[3], $activeDerivatives[0]);
            if (abs($distance1 - $distance3) > 0.4 * min($distance1, $distance3)) {
                $this->logger?->debug(' -> Distance of sides 1 and 3 more than 40% apart', [$distance1, $distance3]);

                continue;
            }

            // 2. Check that opposite sides are equally rotated
            $rotation0 = $this->pointService->getRotation($activeDerivatives[0], $activeDerivatives[1]);
            $rotation2 = $this->pointService->getRotation($activeDerivatives[3], $activeDerivatives[2]);
            $rotationDiff0 = abs($this->pointService->normalizeRotation($rotation0 - $rotation2));
            if ($rotationDiff0 > 30) {
                $this->logger?->debug(' -> Rotation of sides 0 and 2 more than 30° apart', [$rotation0, $rotation2]);

                continue;
            }

            $rotation1 = $this->pointService->getRotation($activeDerivatives[1], $activeDerivatives[2]);
            $rotation3 = $this->pointService->getRotation($activeDerivatives[0], $activeDerivatives[3]);
            $rotationDiff1 = abs($this->pointService->normalizeRotation($rotation1 - $rotation3));
            if ($rotationDiff1 > 30) {
                $this->logger?->debug(' -> Rotation of sides 1 and 3 more than 30° apart', [$rotation1, $rotation3]);

                continue;
            }

            // 4. Check that the sides are in their length not too far away from another
            if (abs(min($distance0, $distance2) - min($distance1, $distance3)) > 0.5 * min($distance0, $distance1, $distance2, $distance3)) {
                $this->logger?->debug(' -> Too narrow rectangle', [$distance0, $distance1, $distance2, $distance3]);

                continue;
            }

            // 5. Check that the first 10% are straight to the side
            for ($i = 0; $i < 4; ++$i) {
                $sideDistance = $this->pointService->getDistanceBetweenPoints($activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
                $extendedPoints = $this->pathService->extendPointsByCount(
                    $this->getPointsBetweenIndexes($borderPoints, $activeDerivatives[$i]->getIndex(), $activeDerivatives[($i + 1) % 4]->getIndex()),
                    100
                );

                for ($j = 0; $j < 10; ++$j) {
                    $distanceToLine = $this->pointService->getDistanceToLine($extendedPoints[$j], $activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
                    if ($distanceToLine > 0.05 * $sideDistance) {
                        $this->logger?->debug(' -> Starting part of side ' . $i . ' not straight at point ' . $j, [$distanceToLine, $sideDistance]);

                        continue 3;
                    }
                }
                for ($j = 90; $j < 100; ++$j) {
                    $distanceToLine = $this->pointService->getDistanceToLine($extendedPoints[$j], $activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
                    if ($distanceToLine > 0.05 * $sideDistance) {
                        $this->logger?->debug(' -> Ending part of side ' . $i . ' not straight at point ' . $j, [$distanceToLine, $sideDistance]);

                        continue 3;
                    }
                }
            }

            $this->logger?->debug(' -> Fitting!');

            foreach ($activeDerivatives as $derivative) {
                $derivative->setUsedAsCorner(true);
            }

            return [
                new Side($this->getPointsBetweenIndexes($borderPoints, $activeDerivatives[0]->getIndex(), $activeDerivatives[1]->getIndex())),
                new Side($this->getPointsBetweenIndexes($borderPoints, $activeDerivatives[1]->getIndex(), $activeDerivatives[2]->getIndex())),
                new Side($this->getPointsBetweenIndexes($borderPoints, $activeDerivatives[2]->getIndex(), $activeDerivatives[3]->getIndex())),
                new Side($this->getPointsBetweenIndexes($borderPoints, $activeDerivatives[3]->getIndex(), $activeDerivatives[0]->getIndex())),
            ];
        }

        $this->logger?->error('Not recognized as piece');

        return [];
    }

    /**
     * @param DerivativePoint[] $derivativePoints
     *
     * @return iterable<DerivativePoint[]>
     */
    private function iterateOverBestDerivativePoints(array $derivativePoints): iterable
    {
        $count = count($derivativePoints);

        // We search for the extremes (=lowest derivations)
        $filteredDerivativePoints = [];
        $currentDirection = null;
        for ($i = 1; $i <= $count + 1; ++$i) {
            $direction = $derivativePoints[$i % $count]->getDerivative() < $derivativePoints[($i - 1) % $count]->getDerivative() ? -1 : 1;
            if ($currentDirection === null) {
                $currentDirection = $direction;

                continue;
            }

            $currentIndex = ($i - 1) % $count;
            if ($direction === 1 && $currentDirection === -1 && $derivativePoints[$currentIndex]->getDerivative() < self::DERIVATIVE_LIMIT) {
                $lowestDerivativeInNeighbourhood = 0;
                $lowestIndex = null;
                for ($j = -self::DERIVATIVE_NEIGHBOUR_LOOKAHEAD; $j <= self::DERIVATIVE_NEIGHBOUR_LOOKAHEAD; ++$j) {
                    $index = ($currentIndex + $j + $count) % $count;
                    if ($derivativePoints[$index]->getDerivative() < $lowestDerivativeInNeighbourhood) {
                        $lowestDerivativeInNeighbourhood = $derivativePoints[$index]->getDerivative();
                        $lowestIndex = $index;
                    }
                }

                if ($lowestIndex === $currentIndex) {
                    $filteredDerivativePoints[] = $derivativePoints[$currentIndex];
                    $derivativePoints[$currentIndex]->setExtreme(true);
                }
            }

            $currentDirection = $direction;
        }

        asort($filteredDerivativePoints);

        $filteredDerivativePoints = array_values($filteredDerivativePoints);
        $filteredCount = count($filteredDerivativePoints);

        for ($i4 = 3; $i4 < $filteredCount; ++$i4) {
            for ($i3 = 2; $i3 < $i4; ++$i3) {
                for ($i2 = 1; $i2 < $i3; ++$i2) {
                    for ($i1 = 0; $i1 < $i2; ++$i1) {
                        $activeDerivatives = [
                            $filteredDerivativePoints[$i1],
                            $filteredDerivativePoints[$i2],
                            $filteredDerivativePoints[$i3],
                            $filteredDerivativePoints[$i4],
                        ];

                        usort($activeDerivatives, fn (DerivativePoint $a, DerivativePoint $b): int => $a->getIndex() <=> $b->getIndex());

                        yield $activeDerivatives;
                    }
                }
            }
        }
    }

    /**
     * @param Point[] $points
     *
     * @return DerivativePoint[]
     */
    private function getDerivativePoints(array $points): array
    {
        $derivativePoints = [];
        $length = 0;
        foreach ($points as $index => $point) {
            $pointBefore = $this->pathService->getPointOnPolyline($points, $index, -$this->derivationLookahead);
            $pointAfter = $this->pathService->getPointOnPolyline($points, $index, $this->derivationLookahead);

            $rotationBefore = $this->pointService->getRotation($pointBefore, $point);
            $rotationAfter = $this->pointService->getRotation($point, $pointAfter);

            $derivativePoints[] = new DerivativePoint(
                $point->getX(),
                $point->getY(),
                $this->pointService->normalizeRotation($rotationAfter - $rotationBefore),
                $index
            );

            $length += $this->pointService->getDistanceBetweenPoints($point, $pointAfter);
        }

        return $derivativePoints;
    }

    /**
     * @param Point[] $points
     *
     * @return Point[]
     */
    private function getPointsBetweenIndexes(array $points, int $index1, int $index2): array
    {
        if ($index2 >= $index1) {
            return array_slice($points, $index1, $index2 - $index1 + 1);
        }

        return array_merge(
            array_slice($points, $index1),
            array_slice($points, 0, $index2 + 1)
        );
    }
}
