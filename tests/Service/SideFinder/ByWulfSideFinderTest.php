<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideFinder;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use PHPUnit\Framework\TestCase;

class ByWulfSideFinderTest extends TestCase
{
    private ByWulfSideFinder $sideFinder;

    protected function setUp(): void
    {
        $this->sideFinder = new ByWulfSideFinder();
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

        for ($i = 0; $i < count($borderPoints); ++$i) {
            $this->assertEquals(($i + 1) % 30 === 0, $borderPoints[$i]->isExtreme());
        }
    }
}
