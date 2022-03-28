<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Service\PieceAnalyzer;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Service\PieceAnalyzer
 */
class PieceAnalyzerTest extends TestCase
{
    public function testGetPieceFromImage(): void
    {
        $pieceAnalyzer = new PieceAnalyzer(new ByWulfBorderFinder(), new ByWulfSideFinder());

        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/piece3.jpg');
        $transparentImage = imagecreatefromjpeg(__DIR__ . '/../fixtures/piece3_color.jpg');
        $smallTransparentImage = imagecreatefromjpeg(__DIR__ . '/../fixtures/piece3_color.jpg');
        $context = new ByWulfBorderFinderContext(0.65, $transparentImage, $smallTransparentImage);

        $piece = $pieceAnalyzer->getPieceFromImage(3, $image, $context);
        $expectedPiece = Piece::fromSerialized(file_get_contents(__DIR__ . '/../fixtures/piece3_piece.ser'));

        $this->assertEquals($expectedPiece, $piece);
        $this->assertEquals(imagecreatefromjpeg(__DIR__ . '/../fixtures/piece3.jpg'), $image);
        $this->assertEquals(imagecreatefrompng(__DIR__ . '/../fixtures/piece3_transparent.png'), $transparentImage);
        $this->assertEquals(imagecreatefrompng(__DIR__ . '/../fixtures/piece3_transparent_small.png'), $smallTransparentImage);
    }
}
