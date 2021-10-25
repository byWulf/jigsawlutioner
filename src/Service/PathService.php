<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Point;
use PointReduction\Algorithms\RamerDouglasPeucker;
use PointReduction\Common\Point as ReductionPoint;

class PathService
{
    /**
     * @param Point[] $points
     * @return Point[]
     */
    public function simplifyPoints(array $points): array
    {
        $algoPoints = array_map(fn(Point $point): ReductionPoint => new ReductionPoint($point->getX(), $point->getY()), $points);
        $reducer = new RamerDouglasPeucker($algoPoints);
        $reducedPoints = $reducer->reduce(2);
        return array_map(fn(ReductionPoint $point): Point => new Point((int) round($point->x), (int) round($point->y)), $reducedPoints);
    }
}