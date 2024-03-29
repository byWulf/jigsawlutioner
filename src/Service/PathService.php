<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\PathServiceException;

class PathService
{
    private PointService $pointService;

    public function __construct()
    {
        $this->pointService = new PointService();
    }

    /**
     * @param Point[] $points
     *
     * @return Point[]
     */
    public function extendPointsByCount(array $points, int $count): array
    {
        if (count($points) === 0) {
            return [];
        }

        $polylineLength = $this->getLengthOfPolyline($points);
        $distance = $polylineLength / ($count - 1);

        $extendedPoints = $this->extendPointsByDistance($points, $distance);

        if (count($extendedPoints) < $count) {
            $extendedPoints[] = $points[count($points) - 1];
        }

        return $extendedPoints;
    }

    /**
     * @param Point[] $points
     *
     * @return Point[]
     */
    public function extendPointsByDistance(array $points, float $distance): array
    {
        $pointsCount = count($points);
        if ($pointsCount === 0) {
            return [];
        }

        $extendedPoints = [$points[0]];
        $offset = 0;
        for ($i = 0; $i < $pointsCount - 1; ++$i) {
            $lineLength = $this->pointService->getDistanceBetweenPoints($points[$i], $points[$i + 1]);
            while ($offset <= $lineLength - $distance) {
                $offset += $distance;
                if ($offset !== 0.0) {
                    $extendedPoints[] = new Point(
                        $points[$i]->getX() + ($points[$i + 1]->getX() - $points[$i]->getX()) / $lineLength * $offset,
                        $points[$i]->getY() + ($points[$i + 1]->getY() - $points[$i]->getY()) / $lineLength * $offset,
                    );
                }
            }
            $offset -= $lineLength;
        }

        return $extendedPoints;
    }

    /**
     * @param Point[] $points
     */
    public function getPointOnPolyline(array $points, int $fromPointIndex, float $length): Point
    {
        if (count($points) < 2) {
            throw new PathServiceException('At least two points must be given.');
        }

        if (!isset($points[$fromPointIndex])) {
            throw new PathServiceException('Given index out of range of the given points.');
        }

        $indexDirection = $length < 0 ? -1 : 1;
        $movedLength = 0;
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $length = abs($length);
        $point = null;
        while ($point === null) {
            $nextIndex = $fromPointIndex + $indexDirection;
            $nextIndex = $nextIndex < 0 ? count($points) - 1 : $nextIndex;
            $nextIndex = $nextIndex >= count($points) ? 0 : $nextIndex;
            $lineLength = $this->pointService->getDistanceBetweenPoints($points[$fromPointIndex], $points[$nextIndex]);

            if ($movedLength + $lineLength >= $length) {
                $offset = $length - $movedLength;

                $point = new Point(
                    $points[$fromPointIndex]->getX() + ($points[$nextIndex]->getX() - $points[$fromPointIndex]->getX()) / $lineLength * $offset,
                    $points[$fromPointIndex]->getY() + ($points[$nextIndex]->getY() - $points[$fromPointIndex]->getY()) / $lineLength * $offset,
                );
            }
            $movedLength += $lineLength;
            $fromPointIndex = $nextIndex;
        }

        return $point;
    }

    /**
     * @param Point[] $points
     */
    private function getLengthOfPolyline(array $points): float
    {
        $length = 0;
        $pointsCount = count($points);

        for ($i = 0; $i < $pointsCount - 1; ++$i) {
            $length += $this->pointService->getDistanceBetweenPoints($points[$i], $points[$i + 1]);
        }

        return $length;
    }

    /**
     * @param Point[] $points
     *
     * @return Point[]
     */
    public function softenPolyline(array $points, int $lookAroundDistance, int $targetPointsCount): array
    {
        if (count($points) <= 1) {
            return $points;
        }

        $points = $this->extendPointsByDistance($points, 1);

        $softenedPoints = [];
        $softenedPoints[] = new Point($points[0]->getX(), $points[0]->getY());
        foreach (array_keys($points) as $index) {
            $xSum = 0;
            $ySum = 0;
            $count = 0;
            for ($offsetIndex = -$lookAroundDistance; $offsetIndex <= $lookAroundDistance; ++$offsetIndex) {
                if (!isset($points[$index + $offsetIndex])) {
                    continue;
                }
                $xSum += $points[$index + $offsetIndex]->getX();
                $ySum += $points[$index + $offsetIndex]->getY();
                ++$count;
            }
            $softenedPoints[] = new Point($xSum / $count, $ySum / $count);
        }
        $softenedPoints[] = new Point($points[count($points) - 1]->getX(), $points[count($points) - 1]->getY());

        return $this->extendPointsByCount($softenedPoints, $targetPointsCount);
    }

    /**
     * @param Point[] $points
     *
     * @return Point[]
     */
    public function rotatePointsToCenter(array $points): array
    {
        if (count($points) === 0) {
            return [];
        }

        $startPoint = $points[0];
        $endPoint = $points[count($points) - 1];
        $zeroPoint = new Point(($startPoint->getX() + $endPoint->getX()) / 2, ($startPoint->getY() + $endPoint->getY()) / 2);

        $rotation = atan2($endPoint->getY() - $startPoint->getY(), $endPoint->getX() - $startPoint->getX());
        $rotationSin = sin($rotation);
        $rotationCos = cos($rotation);

        $rotatedPoints = [];
        foreach ($points as $point) {
            $rotatedPoints[] = new Point(
                ($point->getX() - $zeroPoint->getX()) * $rotationCos + ($point->getY() - $zeroPoint->getY()) * $rotationSin,
                ($point->getY() - $zeroPoint->getY()) * $rotationCos - ($point->getX() - $zeroPoint->getX()) * $rotationSin,
            );
        }

        return $rotatedPoints;
    }
}
