<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Service\MathService;
use PHPUnit\Framework\TestCase;

class MathServiceTest extends TestCase
{
    private MathService $mathService;

    protected function setUp(): void
    {
        $this->mathService = new MathService();
    }

    /**
     * @param Point[] $points
     * @param Point[] $expectedPoints
     *
     * @dataProvider getDistanceToLineDataSets
     */
    public function testDistanceToLine(Point $point, Point $lineStart, Point $lineEnd, float $expectedDistance): void
    {
        $result = $this->mathService->getDistanceToLine($point, $lineStart, $lineEnd);

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
}
