<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideFinder;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\PathService;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use Bywulf\Jigsawlutioner\Tests\Helper\NullLogger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder
 */
class ByWulfSideFinderTest extends TestCase
{
    private ByWulfSideFinder $sideFinder;

    protected function setUp(): void
    {
        $this->sideFinder = new ByWulfSideFinder();
        $this->sideFinder->setLogger(new NullLogger());
        //$this->sideFinder->setLogger(new \Bywulf\Jigsawlutioner\Tests\Helper\EchoLogger());
    }

    /**
     * @throws SideParsingException
     */
    public function testGetSidesSimple(): void
    {
        $borderPoints = [];
        $expectedSidePoints = [];
        for ($i = 0; $i <= 30; ++$i) {
            if ($i > 0) {
                $borderPoints[] = new Point(0, $i);
            }
            $expectedSidePoints[3][] = new Point(0, $i);
        }
        for ($i = 0; $i <= 30; ++$i) {
            if ($i > 0) {
                $borderPoints[] = new Point($i, 30);
            }
            $expectedSidePoints[0][] = new Point($i, 30);
        }
        for ($i = 30; $i >= 0; --$i) {
            if ($i < 30) {
                $borderPoints[] = new Point(30, $i);
            }
            $expectedSidePoints[1][] = new Point(30, $i);
        }
        for ($i = 30; $i >= 0; --$i) {
            if ($i < 30) {
                $borderPoints[] = new Point($i, 0);
            }
            $expectedSidePoints[2][] = new Point($i, 0);
        }

        $sides = $this->sideFinder->getSides($borderPoints);

        $this->assertCount(4, $sides);
        foreach ($sides as $sideIndex => $side) {
            $this->assertCount(31, $side->getPoints());
            foreach ($side->getPoints() as $pointIndex => $point) {
                $this->assertEquals($expectedSidePoints[$sideIndex][$pointIndex]->getX(), $point->getX());
                $this->assertEquals($expectedSidePoints[$sideIndex][$pointIndex]->getY(), $point->getY());
            }
        }

        foreach ($borderPoints as $i => $point) {
            $this->assertEquals(($i + 1) % 30 === 0, $point->isExtreme());
        }
    }

    /**
     * @throws SideParsingException
     */
    public function testGetSidesUnsolvableCircle(): void
    {
        $points = [];
        for ($i = 0; $i < 360; $i++) {
            $points[] = new Point(cos(deg2rad($i)), sin(deg2rad($i)));
        }

        $this->assertEmpty($this->sideFinder->getSides($points));
    }

    /**
     * @dataProvider getSidesUnsolvableProvider
     */
    public function testGetSidesUnsolvable(Point ...$points): void
    {
        $pathService = new PathService();

        $pointGroups = [];
        $count = count($points);
        foreach (array_keys($points) as $i) {
            $pointGroups[] = $pathService->extendPointsByCount([$points[$i], $points[($i + 1) % $count]], 100);
        }

        $extendedPoints = array_merge(...$pointGroups);
        $this->assertEmpty($this->sideFinder->getSides($extendedPoints));
    }

    public function getSidesUnsolvableProvider(): array
    {
        return [
            'Too narrow rectangle (horizontal)' => [new Point(0, 0), new Point(0, 100), new Point(50, 100), new Point(50, 0)],
            'Too narrow rectangle (vertical)' => [new Point(50, 0), new Point(0, 0), new Point(0, 100), new Point(50, 100)],
            'Distance of sides 0 and 2 more than 60% apart' => [new Point(0, 0), new Point(0, 100), new Point(100, 70), new Point(100, 30)],
            'Distance of sides 1 and 3 more than 60% apart' => [new Point(0, 0), new Point(0, 100), new Point(100, 140), new Point(100, 100), ],
            'Starting part not straight' => [new Point(0, 0), new Point(0, 100), new Point(100, 100), new Point(100, 0), new Point(70, 30)],
            'Ending part not straight' => [new Point(0, 0), new Point(0, 100), new Point(100, 100), new Point(100, 0), new Point(30, 30)],
        ];
    }
}
