<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\LineDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Side
 */
class SideTest extends TestCase
{
    public function testGetPoints(): void
    {
        $points = [new Point(1, 2), new Point(3, 4)];
        $side = new Side($points, new Point(4, 5), new Point(5, 6));

        $this->assertEquals($points, $side->getPoints());

        $newPoints = [new Point(6, 7), new Point(7, 8)];
        $side->setPoints($newPoints);

        $this->assertEquals($newPoints, $side->getPoints());
    }

    public function testGetClassifiers(): void
    {
        $side = new Side([], new Point(4, 5), new Point(5, 6));

        $directionClassifier = new DirectionClassifier(DirectionClassifier::NOP_INSIDE);
        $side->addClassifier($directionClassifier);

        $depthClassifier = new DepthClassifier(DirectionClassifier::NOP_INSIDE, 10);
        $side->addClassifier($depthClassifier);

        $this->assertEquals([DirectionClassifier::class => $directionClassifier, DepthClassifier::class => $depthClassifier], $side->getClassifiers());
    }

    public function testGetClassifier(): void
    {
        $side = new Side([], new Point(4, 5), new Point(5, 6));

        $directionClassifier = new DirectionClassifier(DirectionClassifier::NOP_INSIDE);
        $side->addClassifier($directionClassifier);

        $depthClassifier = new DepthClassifier(DirectionClassifier::NOP_INSIDE, 10);
        $side->addClassifier($depthClassifier);

        $this->assertEquals($depthClassifier, $side->getClassifier(DepthClassifier::class));
    }

    public function testGetClassifierNotExisting(): void
    {
        $side = new Side([], new Point(4, 5), new Point(5, 6));

        $directionClassifier = new DirectionClassifier(DirectionClassifier::NOP_INSIDE);
        $side->addClassifier($directionClassifier);

        $this->expectException(SideClassifierException::class);
        $side->getClassifier(DepthClassifier::class);
    }

    public function testGetDirection(): void
    {
        $side = new Side([], new Point(4, 5), new Point(5, 6));

        $directionClassifier = new DirectionClassifier(DirectionClassifier::NOP_INSIDE);
        $side->addClassifier($directionClassifier);

        $depthClassifier = new DepthClassifier(DirectionClassifier::NOP_INSIDE, 10);
        $side->addClassifier($depthClassifier);

        $this->assertEquals(DirectionClassifier::NOP_INSIDE, $side->getDirection());
    }

    public function testGetStartPoint(): void
    {
        $side = new Side([], new Point(4, 5), new Point(5, 6));

        $this->assertEquals(new Point(4, 5), $side->getStartPoint());
    }

    public function testGetEndPoint(): void
    {
        $side = new Side([], new Point(4, 5), new Point(5, 6));

        $this->assertEquals(new Point(5, 6), $side->getEndPoint());
    }

    public function testGetUnrotatedPoints(): void
    {
        $points = [new Point(1, 2), new Point(3, 4)];
        $side = new Side([new Point(1, 1)], new Point(4, 5), new Point(5, 6));

        $this->assertEquals([], $side->getUnrotatedPoints());

        $side->setUnrotatedPoints($points);

        $this->assertEquals($points, $side->getUnrotatedPoints());
    }

    public function testJsonSerialize(): void
    {
        $side = new Side([new Point(4, 5)], new Point(5, 6), new Point(6, 7));

        $side->addClassifier(new BigWidthClassifier(DirectionClassifier::NOP_INSIDE, 10, new Point(4, 5)));
        $side->addClassifier(new CornerDistanceClassifier(10));
        $side->addClassifier(new DepthClassifier(DirectionClassifier::NOP_INSIDE, 10));
        $side->addClassifier(new DirectionClassifier(DirectionClassifier::NOP_INSIDE));
        $side->addClassifier(new LineDistanceClassifier(DirectionClassifier::NOP_INSIDE, 5, 0, 10));
        $side->addClassifier(new SmallWidthClassifier(DirectionClassifier::NOP_INSIDE, 10, new Point(4, 5)));

        $this->assertEquals([
            'points' => [
                [
                    'x' => 4,
                    'y' => 5,
                ],
            ],
            'classifiers' => [
                BigWidthClassifier::class => [
                    'direction' => DirectionClassifier::NOP_INSIDE,
                    'width' => 10,
                    'centerPoint' => [
                        'x' => 4,
                        'y' => 5,
                    ],
                ],
                CornerDistanceClassifier::class => 10,
                DepthClassifier::class => 10,
                DirectionClassifier::class => DirectionClassifier::NOP_INSIDE,
                LineDistanceClassifier::class => [
                    'direction' => DirectionClassifier::NOP_INSIDE,
                    'averageLineDistance' => 5,
                    'minLineDistance' => 0,
                    'maxLineDistance' => 10,
                ],
                SmallWidthClassifier::class => [
                    'direction' => DirectionClassifier::NOP_INSIDE,
                    'width' => 10,
                    'centerPoint' => [
                        'x' => 4,
                        'y' => 5,
                    ],
                ],
            ],
            'startPoint' => [
                'x' => 5,
                'y' => 6,
            ],
            'endPoint' => [
                'x' => 6,
                'y' => 7,
            ],
        ], $side->jsonSerialize());
    }

    public function testGetUnserializeClasses(): void
    {
        $this->assertIsArray(Side::getUnserializeClasses());
    }
}
