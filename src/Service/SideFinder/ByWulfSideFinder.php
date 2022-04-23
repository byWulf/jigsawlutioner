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
        $derivativePoints = $this->getDerivativePoints($borderPoints);

        $bestRating = 0;
        $bestDerivatives = null;
        foreach ($this->iterateOverBestDerivativePoints($derivativePoints) as $activeDerivatives) {
            $rating = $this->getDerivativesRating($activeDerivatives, $borderPoints);

            if ($rating !== null && $rating > $bestRating) {
                $bestRating = $rating;
                $bestDerivatives = $activeDerivatives;
            }
        }

        if ($bestDerivatives === null) {
            $this->logger?->error('Not recognized as piece');

            return [];
        }

        $this->logger?->debug('Found best corners!', $bestDerivatives);

        foreach ($bestDerivatives as $derivative) {
            $derivative->setUsedAsCorner(true);
        }

        $sides = [];
        for ($i = 0; $i < 4; ++$i) {
            $sides[] = new Side(
                $this->getPointsBetweenIndexes($borderPoints, $bestDerivatives[$i]->getIndex(), $bestDerivatives[($i + 1) % 4]->getIndex()),
                $bestDerivatives[$i],
                $bestDerivatives[($i + 1) % 4]
            );
        }

        return $sides;
    }

    /**
     * @param DerivativePoint[] $activeDerivatives
     * @param Point[]           $borderPoints
     */
    private function getDerivativesRating(array $activeDerivatives, array $borderPoints): ?float
    {
        $this->logger?->debug('Looking at the following points: ', $activeDerivatives);
        $rating = 0;

        $distances = [];
        for ($i = 0; $i < 4; ++$i) {
            $distances[$i] = $this->pointService->getDistanceBetweenPoints($activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
        }

        // 1. Check that opposite sides are equally long
        $rating += $this->calculateRating(abs($distances[0] - $distances[2]) / (0.4 * min($distances[0], $distances[2])));
        if (abs($distances[0] - $distances[2]) > 0.6 * min($distances[0], $distances[2])) {
            $this->logger?->debug(' -> Distance of sides 0 and 2 more than 60% apart', [$distances[0], $distances[2]]);

            return null;
        }

        $rating += $this->calculateRating(abs($distances[1] - $distances[3]) / (0.4 * min($distances[1], $distances[3])));
        if (abs($distances[1] - $distances[3]) > 0.6 * min($distances[1], $distances[3])) {
            $this->logger?->debug(' -> Distance of sides 1 and 3 more than 60% apart', [$distances[1], $distances[3]]);

            return null;
        }

        // 2. Check that the sides are in their length not too far away from another
        $rating += $this->calculateRating(abs(min($distances[0], $distances[2]) - min($distances[1], $distances[3])) / (0.5 * min($distances)));
        if (abs(min($distances[0], $distances[2]) - min($distances[1], $distances[3])) > 0.75 * min($distances)) {
            $this->logger?->debug(' -> Too narrow rectangle', $distances);

            return null;
        }

        // 3. Check that the first 10% are straight to the side
        if (!$this->areLineStartsStraight($activeDerivatives, $borderPoints, $rating)) {
            return null;
        }

        return $rating;
    }

    /**
     * @param DerivativePoint[] $activeDerivatives
     * @param Point[]           $borderPoints
     */
    private function areLineStartsStraight(array $activeDerivatives, array $borderPoints, float &$rating): bool
    {
        $dividedCount = 100;

        for ($i = 0; $i < 4; ++$i) {
            $sideDistance = $this->pointService->getDistanceBetweenPoints($activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
            $extendedPoints = $this->pathService->extendPointsByCount(
                $this->getPointsBetweenIndexes($borderPoints, $activeDerivatives[$i]->getIndex(), $activeDerivatives[($i + 1) % 4]->getIndex()),
                $dividedCount
            );

            for ($j = 0; $j < $dividedCount * 0.1; ++$j) {
                $distanceToLine = $this->pointService->getDistanceToLine($extendedPoints[$j], $activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
                $rating += $this->calculateRating($distanceToLine / (0.06 * $sideDistance)) * 0.1;
                if ($distanceToLine > 0.075 * $sideDistance) {
                    $this->logger?->debug(' -> Starting part of side ' . $i . ' not straight at point ' . $j, [$distanceToLine, $sideDistance]);

                    return false;
                }
            }
            for ($j = $dividedCount * 0.9; $j < $dividedCount; ++$j) {
                $distanceToLine = $this->pointService->getDistanceToLine($extendedPoints[$j], $activeDerivatives[$i], $activeDerivatives[($i + 1) % 4]);
                $rating += $this->calculateRating($distanceToLine / (0.06 * $sideDistance)) * 0.1;
                if ($distanceToLine > 0.075 * $sideDistance) {
                    $this->logger?->debug(' -> Ending part of side ' . $i . ' not straight at point ' . $j, [$distanceToLine, $sideDistance]);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param DerivativePoint[] $derivativePoints
     *
     * @return iterable<DerivativePoint[]>
     */
    private function iterateOverBestDerivativePoints(array $derivativePoints): iterable
    {
        $filteredCount = count($derivativePoints);

        for ($i4 = 3; $i4 < $filteredCount; ++$i4) {
            for ($i3 = 2; $i3 < $i4; ++$i3) {
                for ($i2 = 1; $i2 < $i3; ++$i2) {
                    for ($i1 = 0; $i1 < $i2; ++$i1) {
                        $activeDerivatives = [
                            $derivativePoints[$i1],
                            $derivativePoints[$i2],
                            $derivativePoints[$i3],
                            $derivativePoints[$i4],
                        ];

                        usort($activeDerivatives, static fn (DerivativePoint $a, DerivativePoint $b): int => $a->getIndex() <=> $b->getIndex());

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
    private function getDerivativePoints(array &$points): array
    {
        $derivativePoints = [];
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
        }

        $points = $derivativePoints;

        return $this->filterForBestDerivativePoints($derivativePoints);
    }

    /**
     * @param DerivativePoint[] $derivativePoints
     *
     * @return DerivativePoint[]
     */
    private function filterForBestDerivativePoints(array $derivativePoints): array
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
                $lowestIndex = $this->getLowestIndexOfDerivative($currentIndex, $derivativePoints);

                if ($lowestIndex === $currentIndex) {
                    $filteredDerivativePoints[] = $derivativePoints[$currentIndex];
                    $derivativePoints[$currentIndex]->setExtreme(true);
                }
            }

            $currentDirection = $direction;
        }

        asort($filteredDerivativePoints);

        return array_values($filteredDerivativePoints);
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

    private function calculateRating(float $value): float
    {
        return 1 - min(1, $value);
    }

    /**
     * @param DerivativePoint[] $derivativePoints
     */
    protected function getLowestIndexOfDerivative(int $currentIndex, array $derivativePoints): ?int
    {
        $count = count($derivativePoints);

        $lowestDerivativeInNeighbourhood = 0;
        $lowestIndex = null;
        for ($j = -self::DERIVATIVE_NEIGHBOUR_LOOKAHEAD; $j <= self::DERIVATIVE_NEIGHBOUR_LOOKAHEAD; ++$j) {
            $index = ($currentIndex + $j + $count) % $count;
            if ($derivativePoints[$index]->getDerivative() < $lowestDerivativeInNeighbourhood) {
                $lowestDerivativeInNeighbourhood = $derivativePoints[$index]->getDerivative();
                $lowestIndex = $index;
            }
        }

        return $lowestIndex;
    }
}
