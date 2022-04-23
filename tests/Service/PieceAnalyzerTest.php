<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext;
use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Service\PieceAnalyzer;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use Bywulf\Jigsawlutioner\Tests\Helper\WrongBorderFinderContext;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Bywulf\Jigsawlutioner\Service\PieceAnalyzer
 */
class PieceAnalyzerTest extends TestCase
{
    use ProphecyTrait;

    public function testGetPieceFromImage(): void
    {
        $borderFinder = $this->prophesize(BorderFinderInterface::class);
        $sideFinder = $this->prophesize(SideFinderInterface::class);

        $pieceAnalyzer = new PieceAnalyzer($borderFinder->reveal(), $sideFinder->reveal());

        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/piece3.jpg');
        $context = new WrongBorderFinderContext();
        $borderPoints = unserialize(file_get_contents(__DIR__ . '/../fixtures/piece3_borderPoints.ser'), ['allowed_classes' => [DerivativePoint::class]]);
        $sides = unserialize(file_get_contents(__DIR__ . '/../fixtures/piece3_sides.ser'), ['allowed_classes' => Side::getUnserializeClasses()]);

        $borderFinder->findPieceBorder($image, $context)
            ->shouldBeCalledOnce()
            ->willReturn($borderPoints);

        $sideFinder->getSides($borderPoints)
            ->shouldBeCalledOnce()
            ->willReturn($sides);

        $piece = $pieceAnalyzer->getPieceFromImage(3, $image, $context);
        $expectedPiece = Piece::fromSerialized(file_get_contents(__DIR__ . '/../fixtures/piece3_piece.ser'));

        $this->assertEquals($expectedPiece, $piece);
    }
}
