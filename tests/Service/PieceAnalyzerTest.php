<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Service\PathService;
use Bywulf\Jigsawlutioner\Service\PieceAnalyzer;
use Bywulf\Jigsawlutioner\Service\SideFinder\ByWulfSideFinder;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionClass;

class PieceAnalyzerTest extends TestCase
{
    use ProphecyTrait;

    public function testGetPieceFromImage(): void
    {
        /** @var BorderFinderInterface|ObjectProphecy $borderFinder */
        $borderFinder = $this->prophesize(BorderFinderInterface::class);
        /** @var SideFinderInterface|ObjectProphecy $sideFinder */
        $sideFinder = $this->prophesize(SideFinderInterface::class);
        /** @var PathService|ObjectProphecy $pathService */
        $pathService = $this->prophesize(PathService::class);

        $image = imagecreate(100, 100);

        $borderPoints = [new Point(0, 1), new Point(1, 2), new Point(2, 3)];
        $borderFinder->findPieceBorder($image)->shouldBeCalledOnce()->willReturn($borderPoints);

        $sideFinder->getSides($borderPoints)->shouldBeCalledOnce()->willReturn([
            new Side([new Point(0, 0)]),
            new Side([new Point(1, 1)]),
            new Side([new Point(2, 2)]),
            new Side([new Point(3, 3)]),
        ]);

        $pathService->softenPolyline([new Point(0, 0)], 10, 100)->shouldBeCalled()->willReturn([new Point(0.5, 0.5)]);
        $pathService->softenPolyline([new Point(1, 1)], 10, 100)->shouldBeCalled()->willReturn([new Point(1.5, 1.5)]);
        $pathService->softenPolyline([new Point(2, 2)], 10, 100)->shouldBeCalled()->willReturn([new Point(2.5, 2.5)]);
        $pathService->softenPolyline([new Point(3, 3)], 10, 100)->shouldBeCalled()->willReturn([new Point(3.5, 3.5)]);

        $pathService->rotatePointsToCenter([new Point(0.5, 0.5)])->shouldBeCalled()->willReturn([new Point(-0.5, -0.5)]);
        $pathService->rotatePointsToCenter([new Point(1.5, 1.5)])->shouldBeCalled()->willReturn([new Point(-1.5, -1.5)]);
        $pathService->rotatePointsToCenter([new Point(2.5, 2.5)])->shouldBeCalled()->willReturn([new Point(-2.5, -2.5)]);
        $pathService->rotatePointsToCenter([new Point(3.5, 3.5)])->shouldBeCalled()->willReturn([new Point(-3.5, -3.5)]);

        $pieceAnalyzer = new PieceAnalyzer($borderFinder->reveal(), $sideFinder->reveal());

        $reflectionClass = new ReflectionClass(PieceAnalyzer::class);
        $reflectionProperty = $reflectionClass->getProperty('pathService');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($pieceAnalyzer, $pathService->reveal());

        $expectedPiece = new Piece(
            $borderPoints,
            [
                new Side([new Point(-0.5, -0.5)]),
                new Side([new Point(-1.5, -1.5)]),
                new Side([new Point(-2.5, -2.5)]),
                new Side([new Point(-3.5, -3.5)]),
            ]
        );

        $this->assertEquals($expectedPiece, $pieceAnalyzer->getPieceFromImage($image));
    }

    public function testFull(): void
    {
        $this->markTestSkipped('Only for manual execution');

        $borderFinder = new ByWulfBorderFinder();
        $sideFinder = new ByWulfSideFinder();
        $pieceAnalyzer = new PieceAnalyzer($borderFinder, $sideFinder);

        $start = 2;
        $max = 501;

        $start = $max = 37;
//        $start = $max = 141;
//        $start = $max = 174;
//        $start = $max = 215;
        //$start = $max = 222;

        //foreach ([37, 141, 174, 215, 222] as $i) {
        for ($i = $start; $i <= $max; ++$i) {
            $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece' . $i . '.jpg');

            try {
                $piece = $pieceAnalyzer->getPieceFromImage($image);

                // Found corners
                $color = imagecolorallocate($image, 0, 0, 0);
                foreach ($piece->getSides() as $side) {
                    for ($x = (int) $side->getPoints()[0]->getX() - 10; $x < $side->getPoints()[0]->getX() + 10; ++$x) {
                        imagesetpixel($image, $x, (int) $side->getPoints()[0]->getY(), $color);
                    }
                    for ($y = (int) $side->getPoints()[0]->getY() - 10; $y < $side->getPoints()[0]->getY() + 10; ++$y) {
                        imagesetpixel($image, (int) $side->getPoints()[0]->getX(), $y, $color);
                    }
                }

                // BorderPoints (red = anti-clockwise, green = clockwise)
                foreach ($piece->getBorderPoints() as $point) {
                    $color = imagecolorallocate($image, 255, 255, 255);
                    if ($point instanceof DerivativePoint) {
                        $diff = (int) min((abs($point->getDerivative()) / 90) * 255, 255);

                        $color = imagecolorallocate(
                            $image,
                            255 - ($point->getDerivative() > 0 ? $diff : 0),
                            255 - ($point->getDerivative() < 0 ? $diff : 0),
                            255 - $diff
                        );
                        if ($point->isExtreme()) {
                            $color = imagecolorallocate($image, 255, 255, 0);
                        }
                    }
                    imagesetpixel($image, (int) $point->getPoint()->getX(), (int) $point->getPoint()->getY(), $color);
                }

                // Smoothed and normalized side points
                foreach ($piece->getSides() as $index => $side) {
                    foreach ($side->getPoints() as $point) {
                        imagesetpixel($image, (int) ($point->getX() / 3) + 100, (int) ($point->getY() / 3) + 50 + $index * 100, imagecolorallocate($image, 50, 80, 255));
                    }
                }

                file_put_contents(__DIR__ . '/../fixtures/pieces/piece' . $i . '_piece.json', json_encode($piece));
                echo 'Piece ' . $i . ' parsed successfully.' . PHP_EOL;
            } catch (BorderParsingException $exception) {
                echo 'Piece ' . $i . ' failed at BorderFinding: ' . $exception->getMessage() . PHP_EOL;
            } catch (SideParsingException $exception) {
                echo 'Piece ' . $i . ' failed at SideFinding: ' . $exception->getMessage() . PHP_EOL;
            } finally {
                imagepng($image, __DIR__ . '/../fixtures/pieces/piece' . $i . '_mask.png');
            }
        }
    }
}
