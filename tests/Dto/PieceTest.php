<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\LineDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Piece
 */
class PieceTest extends TestCase
{
    public function testFromSerialized(): void
    {
        $piece = $this->getPieceMock();
        $serializedPiece = serialize($piece);

        $this->assertEquals($piece, Piece::fromSerialized($serializedPiece));
    }

    public function testFromSerializedInvalidDto(): void
    {
        $object = new stdClass();
        $serializedPiece = serialize($object);

        $this->expectException(InvalidArgumentException::class);
        Piece::fromSerialized($serializedPiece);
    }

    public function testGetIndex(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals(1, $piece->getIndex());
    }

    public function testGetBorderPoints(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals([new DerivativePoint(1, 2, 3, 4)], $piece->getBorderPoints());
    }

    public function testGetSide(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals($this->getSideMock(), $piece->getSide(0));
        $this->assertEquals($this->getSideMock(), $piece->getSide(-4));
    }

    public function testGetSides(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals([$this->getSideMock()], $piece->getSides());

        $piece->setSides([$this->getSideMock(5, 6)]);

        $this->assertEquals([$this->getSideMock(5, 6)], $piece->getSides());
    }

    public function testGetImageWidth(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals(10, $piece->getImageWidth());
    }

    public function testGetImageHeight(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals(15, $piece->getImageHeight());
    }

    public function testReduceData(): void
    {
        $piece = $this->getPieceMock();

        $piece->reduceData();

        $this->assertEquals([], $piece->getBorderPoints());

        $side = $this->getSideMock();
        $side->setPoints([]);
        $side->setUnrotatedPoints([]);
        $this->assertEquals($side, $piece->getSide(0));
    }

    public function testJsonSerialize(): void
    {
        $piece = $this->getPieceMock();

        $this->assertEquals([
            'index' => 1,
            'borderPoints' => [
                [
                    'x' => 1,
                    'y' => 2,
                    'derivative' => 3,
                    'index' => 4,
                    'extreme' => false,
                    'usedAsCorner' => false,
                ],
            ],
            'sides' => [
                [
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
                ],
            ],
            'imageWidth' => 10,
            'imageHeight' => 15,
        ], $piece->jsonSerialize());
    }

    private function getPieceMock(): Piece
    {
        return new Piece(
            1,
            [new DerivativePoint(1, 2, 3, 4)],
            [$this->getSideMock()],
            10,
            15
        );
    }

    private function getSideMock(int $x = 4, int $y = 5): Side
    {
        $side = new Side([new Point($x, $y)], new Point(5, 6), new Point(6, 7));

        $side->addClassifier(new BigWidthClassifier(DirectionClassifier::NOP_INSIDE, 10, new Point(4, 5)));
        $side->addClassifier(new CornerDistanceClassifier(10));
        $side->addClassifier(new DepthClassifier(DirectionClassifier::NOP_INSIDE, 10));
        $side->addClassifier(new DirectionClassifier(DirectionClassifier::NOP_INSIDE));
        $side->addClassifier(new LineDistanceClassifier(DirectionClassifier::NOP_INSIDE, 5, 0, 10));
        $side->addClassifier(new SmallWidthClassifier(DirectionClassifier::NOP_INSIDE, 10, new Point(4, 5)));

        return $side;
    }
}
