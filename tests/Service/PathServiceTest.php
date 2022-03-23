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
     * @dataProvider getExtendPointsDataSets
     */
    public function testExtendPoints(array $points, int $count, array $expectedPoints): void
    {
        $result = $this->pathService->extendPointsByCount($points, $count);

        $this->assertEquals($expectedPoints, $result);
    }

    public function getExtendPointsDataSets(): array
    {
        return [
            [[new Point(0, 0), new Point(20, 0)], 2, [new Point(0, 0), new Point(20, 0)]],
            [[new Point(0, 0), new Point(20, 0)], 3, [new Point(0, 0), new Point(10, 0), new Point(20, 0)]],
            [[new Point(0, 0), new Point(20, 20)], 3, [new Point(0, 0), new Point(10, 10), new Point(20, 20)]],
            [[new Point(0, 0), new Point(20, 0), new Point(20, 20)], 3, [new Point(0, 0), new Point(20, 0), new Point(20, 20)]],
            [[new Point(0, 0), new Point(20, 0), new Point(20, 20)], 5, [new Point(0, 0), new Point(10, 0), new Point(20, 0), new Point(20, 10), new Point(20, 20)]],
        ];
    }
}
