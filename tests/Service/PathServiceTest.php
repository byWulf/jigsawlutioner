<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Service\PathService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Service\PathService
 */
class PathServiceTest extends TestCase
{
    private PathService $pathService;

    protected function setUp(): void
    {
        $this->pathService = new PathService();
    }

    /**
     * @param Point[] $points
     * @param Point[] $expectedPoints
     *
     * @dataProvider getExtendPointsByCountProvider
     */
    public function testExtendPointsByCount(array $points, int $count, array $expectedPoints): void
    {
        $result = $this->pathService->extendPointsByCount($points, $count);

        $this->assertEquals($expectedPoints, $result);
    }

    public function getExtendPointsByCountProvider(): array
    {
        return [
            [[], 3, []],
            [[new Point(0, 0), new Point(20, 0)], 2, [new Point(0, 0), new Point(20, 0)]],
            [[new Point(0, 0), new Point(20, 0)], 3, [new Point(0, 0), new Point(10, 0), new Point(20, 0)]],
            [[new Point(0, 0), new Point(20, 20)], 3, [new Point(0, 0), new Point(10, 10), new Point(20, 20)]],
            [[new Point(0, 0), new Point(20, 0), new Point(20, 20)], 3, [new Point(0, 0), new Point(20, 0), new Point(20, 20)]],
            [[new Point(0, 0), new Point(20, 0), new Point(20, 20)], 5, [new Point(0, 0), new Point(10, 0), new Point(20, 0), new Point(20, 10), new Point(20, 20)]],
        ];
    }

    /**
     * @param Point[] $points
     * @param Point[] $expectedPoints
     *
     * @dataProvider getExtendPointsByDistanceProvider
     */
    public function testExtendPointsByDistance(array $points, float $distance, array $expectedPoints): void
    {
        $result = $this->pathService->extendPointsByDistance($points, $distance);

        $this->assertEquals($expectedPoints, $result);
    }

    public function getExtendPointsByDistanceProvider(): array
    {
        return [
            [[], 3, []],
            [[new Point(0, 0), new Point(20, 0)], 5, [new Point(0, 0), new Point(5, 0), new Point(10, 0), new Point(15, 0), new Point(20, 0)]],
            [[new Point(0, 0), new Point(20, 0)], 8, [new Point(0, 0), new Point(8, 0), new Point(16, 0)]],
            [[new Point(0, 0), new Point(3, 4)], 1, [new Point(0, 0), new Point(0.6, 0.8), new Point(1.2, 1.6), new Point(1.8, 2.4), new Point(2.4, 3.2), new Point(3, 4)]],
        ];
    }

    /**
     * @dataProvider softenPolylineProvider
     */
    public function testSoftenPolyline(array $points, int $lookAroundDistance, int $targetPointsCount, array $expectedPoints): void
    {
        $this->assertEquals($expectedPoints, $this->pathService->softenPolyline($points, $lookAroundDistance, $targetPointsCount));
    }

    public function softenPolylineProvider(): array
    {
        return [
            [[], 2, 5, []],
            [[new Point(0, 0)], 2, 5, [new Point(0, 0)]],
            [[new Point(0, 0), new Point(1, 0)], 2, 6, [new Point(0, 0), new Point(0.2, 0), new Point(0.4, 0), new Point(0.6, 0), new Point(0.8, 0), new Point(1, 0)]],
            [[new Point(0, 0), new Point(1, 0), new Point(2, 0), new Point(3, 0), new Point(4, 0), new Point(4, 1), new Point(4, 2), new Point(4, 3), new Point(4, 4)], 2, 3, [new Point(0, 0), new Point(3.4, 0.6), new Point(4, 4)]],
            [[new Point(0, 0), new Point(1, 0), new Point(2, 0), new Point(3, 0), new Point(4, 0), new Point(4, 1), new Point(4, 2), new Point(4, 3), new Point(4, 4)], 2, 5, [new Point(0, 0), new Point(1.772865690108165, 0), new Point(3.4, 0.6), new Point(4, 2.2271343098918353), new Point(4, 4)]],
            [[new Point(0, 0), new Point(1, 0), new Point(2, 0), new Point(3, 0), new Point(4, 0), new Point(4, 1), new Point(4, 2), new Point(4, 3), new Point(4, 4)], 1, 3, [new Point(0, 0), new Point(3.6666666666666665, 0.3333333333333333), new Point(4, 4)]],
            [[new Point(0, 0), new Point(1, 0), new Point(2, 0), new Point(3, 0), new Point(4, 0), new Point(4, 1), new Point(4, 2), new Point(4, 3), new Point(4, 4)], 1, 5, [new Point(0, 0), new Point(1.872677996249965, 0), new Point(3.6666666666666665, 0.3333333333333333), new Point(4, 2.127322003750035), new Point(4, 4)]],
        ];
    }

    /**
     * @dataProvider rotatePointsToCenterProvider
     */
    public function testRotatePointsToCenter(array $points, array $expectedPoints): void
    {
        $this->assertEquals($expectedPoints, $this->pathService->rotatePointsToCenter($points));
    }

    public function rotatePointsToCenterProvider(): array
    {
        return [
            [[], []],
            [[new Point(1, 1)], [new Point(0, 0)]],
            [[new Point(1, 1), new Point(3, 1)], [new Point(-1, 0), new Point(1, 0)]],
            [[new Point(1, 1), new Point(1, 3)], [new Point(-1, 0), new Point(1, 0)]],
            [[new Point(1, 1), new Point(2, 2), new Point(3, 1)], [new Point(-1, 0), new Point(0, 1), new Point(1, 0)]],
            [[new Point(1, 1), new Point(2, 0), new Point(3, 1)], [new Point(-1, 0), new Point(0, -1), new Point(1, 0)]],
            [[new Point(1, 1), new Point(2, 2), new Point(1, 3)], [new Point(-1, 0), new Point(0, -1), new Point(1, 0)]],
            [[new Point(1, 1), new Point(0, 2), new Point(1, 3)], [new Point(-1, 0), new Point(0, 1), new Point(1, 0)]],
        ];
    }
}
