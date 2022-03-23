<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Point;
use InvalidArgumentException;

class PointService
{
    private function getDistanceSquared(Point $point1, Point $point2): float
    {
        return (($point1->getX() - $point2->getX()) ** 2) + (($point1->getY() - $point2->getY()) ** 2);
    }

    /**
     * @see https://stackoverflow.com/questions/849211/shortest-distance-between-a-point-and-a-line-segment
     */
    private function getDistanceToSegmentSquared(Point $point, Point $lineStart, Point $lineEnd): float
    {
        $lengthSquared = $this->getDistanceSquared($lineStart, $lineEnd);

        if ($lengthSquared === 0.0) {
            return $this->getDistanceSquared($point, $lineStart);
        }

        $t = (($point->getX() - $lineStart->getX()) * ($lineEnd->getX() - $lineStart->getX()) + ($point->getY() - $lineStart->getY()) * ($lineEnd->getY() - $lineStart->getY())) / $lengthSquared;
        $t = max(0, min(1, $t));

        return $this->getDistanceSquared($point, new Point(($lineStart->getX() + $t * ($lineEnd->getX() - $lineStart->getX())), ($lineStart->getY() + $t * ($lineEnd->getY() - $lineStart->getY()))));
    }

    public function getDistanceToLine(Point $point, Point $lineStart, Point $lineEnd): float
    {
        return sqrt($this->getDistanceToSegmentSquared($point, $lineStart, $lineEnd));
    }

    public function getDistanceBetweenPoints(Point $point1, Point $point2): float
    {
        return sqrt((($point2->getX() - $point1->getX()) ** 2) + (($point2->getY() - $point1->getY()) ** 2));
    }

    public function getRotation(Point $point1, Point $point2): float
    {
        $rotation = rad2deg(atan2($point2->getY() - $point1->getY(), $point2->getX() - $point1->getX()));

        return $this->normalizeRotation($rotation);
    }

    public function normalizeRotation(float $rotation): float
    {
        while ($rotation > 180) {
            $rotation -= 360;
        }
        while ($rotation <= -180) {
            $rotation += 360;
        }

        return $rotation;
    }

    /**
     * @param Point[] $points
     */
    public function getAveragePoint(array $points): Point
    {
        if (count($points) === 0) {
            throw new InvalidArgumentException('You have to provide at least one point to get their average.');
        }

        $xSum = 0;
        $ySum = 0;
        foreach ($points as $point) {
            $xSum += $point->getX();
            $ySum += $point->getY();
        }

        return new Point(
            $xSum / count($points),
            $ySum / count($points)
        );
    }

    public function getAverageRotation(Point $topLeftPoint, Point $bottomLeftPoint, Point $bottomRightPoint, Point $topRightPoint): float
    {
        $topRotation = $this->getRotation($topLeftPoint, $topRightPoint);
        $bottomRotation = $this->justifyRotation($topRotation, $this->getRotation($bottomLeftPoint, $bottomRightPoint));
        $leftRotation = $this->justifyRotation($topRotation, $this->getRotation($topLeftPoint, $bottomLeftPoint) - 90);
        $rightRotation = $this->justifyRotation($topRotation, $this->getRotation($topRightPoint, $bottomRightPoint) - 90);

        return $this->normalizeRotation(($topRotation + $bottomRotation + $leftRotation + $rightRotation) / 4);
    }

    public function justifyRotation(float $baseRotation, float $rotationToJustify): float
    {
        while (abs($rotationToJustify - $baseRotation) > abs($rotationToJustify - $baseRotation - 180)) {
            $rotationToJustify -= 360;
        }

        while (abs($rotationToJustify - $baseRotation) > abs($rotationToJustify - $baseRotation + 180)) {
            $rotationToJustify += 360;
        }

        return $rotationToJustify;
    }

    public function movePoint(Point $point, float $directionDegree, float $length): Point
    {
        $adjustedPoint = clone $point;

        $adjustedPoint->setX($adjustedPoint->getX() + cos(deg2rad($directionDegree)) * $length);
        $adjustedPoint->setY($adjustedPoint->getY() + sin(deg2rad($directionDegree)) * $length);

        return $adjustedPoint;
    }

    public function getIntersectionPointOfLines(Point $line1StartPoint, Point $line1EndPoint, Point $line2StartPoint, Point $line2EndPoint): ?Point
    {
        $x1 = $line1StartPoint->getX();
        $y1 = $line1StartPoint->getY();

        $x2 = $line1EndPoint->getX();
        $y2 = $line1EndPoint->getY();

        if ($x1 === $x2 && $y1 === $y2) {
            return null;
        }

        $x3 = $line2StartPoint->getX();
        $y3 = $line2StartPoint->getY();

        $x4 = $line2EndPoint->getX();
        $y4 = $line2EndPoint->getY();

        if ($x3 === $x4 && $y3 === $y4) {
            return null;
        }

        $denominator = (($x1 - $x2) * ($y3 - $y4)) - (($y1 - $y2) * ($x3 - $x4));

        if ($denominator === 0.0) {
            return null;
        }

        $intersectionX = (($x1 * $y2 - $y1 * $x2) * ($x3 - $x4) - ($x1 - $x2) * ($x3 * $y4 - $x4 * $y3)) / $denominator;
        $intersectionY = (($x1 * $y2 - $y1 * $x2) * ($y3 - $y4) - ($y1 - $y2) * ($x3 * $y4 - $y3 * $x4)) / $denominator;

        return new Point($intersectionX, $intersectionY);
    }
}
