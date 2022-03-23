<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Service\PointService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\
 * @covers \Bywulf\Jigsawlutioner\Service\PointService
 */
class PointServiceTest extends TestCase
{
    private PointService $pointService;

    protected function setUp(): void
    {
        $this->pointService = new PointService();
    }

    /**
     * @dataProvider getDistanceToLineDataSets
     */
    public function testDistanceToLine(Point $point, Point $lineStart, Point $lineEnd, float $expectedDistance): void
    {
        $result = $this->pointService->getDistanceToLine($point, $lineStart, $lineEnd);

        $this->assertEquals($expectedDistance, $result);
    }

    public function getDistanceToLineDataSets(): array
    {
        return [
            [new Point(0, 0), new Point(0, 0), new Point(0, 0), 0],
            [new Point(0, 0), new Point(0, 0), new Point(10, 0), 0],
            [new Point(10, 0), new Point(0, 0), new Point(10, 0), 0],
            [new Point(5, 0), new Point(0, 0), new Point(10, 0), 0],
            [new Point(0, 5), new Point(0, 0), new Point(10, 0), 5],
            [new Point(5, 5), new Point(0, 0), new Point(10, 0), 5],
            [new Point(10, 5), new Point(0, 0), new Point(10, 0), 5],
            [new Point(-5, 0), new Point(0, 0), new Point(10, 0), 5],
            [new Point(15, 0), new Point(0, 0), new Point(10, 0), 5],
            [new Point(5, 5), new Point(0, 0), new Point(10, 10), 0],

            // TODO
        ];
    }

    /**
     * @dataProvider getAverageRotationDataSets
     */
    public function testGetAverageRotation(Point $topLeftPoint, Point $bottomLeftPoint, Point $bottomRightPoint, Point $topRightPoint, float $expectedRotation): void
    {
        $this->assertEquals($expectedRotation, $this->pointService->getAverageRotation($topLeftPoint, $bottomLeftPoint, $bottomRightPoint, $topRightPoint));
    }

    public function getAverageRotationDataSets(): array
    {
        return [
            [new Point(-1, -1), new Point(-1, 1), new Point(1, 1), new Point(1, -1), 0],
            [new Point(-1, 1), new Point(1, 1), new Point(1, -1), new Point(-1, -1), -90],
            [new Point(1, 1), new Point(1, -1), new Point(-1, -1), new Point(-1, 1), 180],
            [new Point(1, -1), new Point(-1, -1), new Point(-1, 1), new Point(1, 1), 90],
            [new Point(-1, -1), new Point(-1, 1), new Point(1, 1), new Point(1, -1), 0],
            [new Point(5, 0), new Point(5, 3), new Point(7, 3), new Point(7, 0), 0],
            [new Point(-2, -2), new Point(-1, 1), new Point(1, 1), new Point(2, -2), 0],
            [new Point(-1, -2), new Point(-1, 0), new Point(2, 2), new Point(2, -4), 0],
            [new Point(-2, 4), new Point(1, 7), new Point(4, 4), new Point(1, 1), -45],
        ];
    }

    /**
     * @dataProvider movePointProvider
     */
    public function testMovePoint(Point $point, float $directionDegree, float $length, Point $expectedPoint): void
    {
        $this->assertEquals($expectedPoint, $this->pointService->movePoint($point, $directionDegree, $length));
    }

    public function movePointProvider(): array
    {
        return [
            [new Point(0, 0), 0, 5, new Point(5, 0)],
            [new Point(0, 0), 90, 5, new Point(0, 5)],
            [new Point(0, 0), 180, 5, new Point(-5, 0)],
            [new Point(0, 0), 270, 5, new Point(0, -5)],
            [new Point(0, 0), -90, 5, new Point(0, -5)],
            [new Point(0, 0), -180, 5, new Point(-5, 0)],
            [new Point(0, 0), -270, 5, new Point(0, 5)],
            [new DerivativePoint(0, 0, 2.5, 3), 0, 5, new DerivativePoint(5, 0, 2.5, 3)],
            [new Point(0, 0), 45, 5, new Point(sqrt(2) * 0.5 * 5, sqrt(2) * 0.5 * 5)],
        ];
    }

    /**
     * @dataProvider getIntersectionPointOfLinesProvider
     */
    public function testGetIntersectionPointOfLines(Point $line1StartPoint, Point $line1EndPoint, Point $line2StartPoint, Point $line2EndPoint, ?Point $expectedPoint): void
    {
        $this->assertEquals($expectedPoint, $this->pointService->getIntersectionPointOfLines($line1StartPoint, $line1EndPoint, $line2StartPoint, $line2EndPoint));
    }

    public function getIntersectionPointOfLinesProvider(): array
    {
        return [
            [new Point(-1, 0), new Point(1, 0), new Point(0, -1), new Point(0, 1), new Point(0, 0)],
            [new Point(1, 0), new Point(-1, 0), new Point(0, -1), new Point(0, 1), new Point(0, 0)],
            [new Point(-1, 0), new Point(1, 0), new Point(0, 1), new Point(0, -1), new Point(0, 0)],
            [new Point(1, 0), new Point(-1, 0), new Point(0, 1), new Point(0, -1), new Point(0, 0)],
            [new Point(-5, 0), new Point(0, 0), new Point(0, -5), new Point(0, 0), new Point(0, 0)],
            [new Point(-5, 0), new Point(-3, 0), new Point(0, -5), new Point(0, -3), new Point(0, 0)],
            [new Point(-1, 0), new Point(1, 0), new Point(-1, 1), new Point(1, 1), null],
            [new Point(-1, 0), new Point(-1, 0), new Point(-1, 1), new Point(1, 1), null],
            [new Point(-1, 0), new Point(1, 0), new Point(-1, 1), new Point(-1, 1), null],
            [new Point(-1, 0), new Point(-1, 0), new Point(-1, 1), new Point(-1, 1), null],
            [new Point(-1, -1), new Point(1, 1), new Point(-1, 1), new Point(1, -1), new Point(0, 0)],
            [new Point(-1, 5), new Point(1, 5), new Point(3, -2), new Point(3, -3), new Point(3, 5)],
        ];
    }
}
