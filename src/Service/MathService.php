<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Point;

class MathService
{
    private function getDistanceSquared(Point $point1, Point $point2): float
    {
        return pow($point1->getX() - $point2->getX(), 2) + pow($point1->getY() - $point2->getY(), 2);
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
        return sqrt(pow($point2->getX() - $point1->getX(), 2) + pow($point2->getY() - $point1->getY(), 2));
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
}
