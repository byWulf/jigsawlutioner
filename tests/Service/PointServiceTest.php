<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Service\PointService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
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

    /**
     * @dataProvider normalizeRotationProvider
     */
    public function testNormalizeRotation(float $rotation, float $expectedRotation): void
    {
        $this->assertEquals($expectedRotation, $this->pointService->normalizeRotation($rotation));
    }

    public function normalizeRotationProvider(): array
    {
        return [
            [0, 0],
            [10, 10],
            [-10, -10],
            [180, 180],
            [181, -179],
            [-179, -179],
            [-180, 180],
            [1260, 180],
            [-1259, -179],
        ];
    }

    /**
     * @dataProvider getAveragePointProvider
     */
    public function testGetAveragePoint(array $points, ?Point $expectedPoint): void
    {
        if ($expectedPoint === null) {
            $this->expectException(InvalidArgumentException::class);
            $this->pointService->getAveragePoint($points);
        } else {
            $this->assertEquals($expectedPoint, $this->pointService->getAveragePoint($points));
        }
    }

    public function getAveragePointProvider(): array
    {
        return [
            [[], null],
            [[new Point(5, 3)], new Point(5, 3)],
            [[new Point(-1, -1), new Point(3, 3)], new Point(1, 1)],
            [[new Point(-1, -1), new Point(1, 1), new Point(-1, 1), new Point(1, -1)], new Point(0, 0)],
        ];
    }

    /**
     * @dataProvider justifyRotationProvider
     */
    public function testJustifyRotation(float $baseRoation, float $rotationToJustify, float $expectedRotation): void
    {
        $this->assertEquals($expectedRotation, $this->pointService->justifyRotation($baseRoation, $rotationToJustify));
    }

    public function justifyRotationProvider(): array
    {
        return [
            [0, 0, 0],
            [360, 0, 360],
            [-360, 0, -360],
            [0, 360, 0],
            [0, -360, 0],
            [-360, -360, -360],
            [-360, 360, -360],
            [360, -360, 360],
            [360, 360, 360],
            [180, 90, 90],
            [180, -90, 270],
            [-180, -360, 0],
            [-180, -270, -270],
            [-180, -180, -180],
            [-180, -90, -90],
            [-180, 0, 0],
            [-180, 90, -270],
        ];
    }
}
