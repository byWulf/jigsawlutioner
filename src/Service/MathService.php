<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Point;

class MathService
{
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