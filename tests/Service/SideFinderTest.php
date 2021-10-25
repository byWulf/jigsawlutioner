<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Border;
use Bywulf\Jigsawlutioner\Dto\BoundingBox;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\JigsawlutionerException;
use Bywulf\Jigsawlutioner\Service\BorderFinder;
use Bywulf\Jigsawlutioner\Service\MathService;
use Bywulf\Jigsawlutioner\Service\PathService;
use Bywulf\Jigsawlutioner\Service\SideFinder;
use PHPUnit\Framework\TestCase;

class SideFinderTest extends TestCase
{
    private SideFinder $sideFinder;
    private BorderFinder $borderFinder;

    protected function setUp(): void
    {
        $this->sideFinder = new SideFinder(new MathService());
        $this->borderFinder = new BorderFinder(new PathService());
    }

    public function testGetSidesSimple(): void
    {
        $border = new Border(
            [
                new Point(0, 0),
                new Point(0, 251),
                new Point(269, 251),
                new Point(269, 0),
            ],
            new BoundingBox(272, 541, 256, 507)
        );

        $sides = $this->sideFinder->getSides($border);


        $this->assertEquals([
            new Point(0, 0),
            new Point(0, 251),
        ], $sides[0]);

        $this->assertEquals([
            new Point(0, 251),
            new Point(269, 251),
        ], $sides[1]);

        $this->assertEquals([
            new Point(269, 251),
            new Point(269, 0),
        ], $sides[2]);

        $this->assertEquals([
            new Point(269, 0),
            new Point(0, 0),
        ], $sides[3]);
    }

    public function testGetSidesComplex(): void
    {
        $border = new Border(
            points: [
                new Point(x: 279, y: 0), // Corner
                new Point(x: 211, y: 61),
                new Point(x: 169, y: 94),
                new Point(x: 152, y: 112),
                new Point(x: 0, y: 238),
                new Point(x: 0, y: 241), // Corner
                new Point(x: 16, y: 250),
                new Point(x: 236, y: 509), // Corner
                new Point(x: 298, y: 456),
                new Point(x: 310, y: 449),
                new Point(x: 325, y: 449),
                new Point(x: 335, y: 463),
                new Point(x: 337, y: 486),
                new Point(x: 343, y: 501),
                new Point(x: 353, y: 509),
                new Point(x: 370, y: 512),
                new Point(x: 387, y: 505),
                new Point(x: 403, y: 493),
                new Point(x: 415, y: 479),
                new Point(x: 422, y: 456),
                new Point(x: 417, y: 443),
                new Point(x: 408, y: 434),
                new Point(x: 382, y: 427),
                new Point(x: 366, y: 418),
                new Point(x: 362, y: 410),
                new Point(x: 364, y: 399),
                new Point(x: 504, y: 266), // Corner
                new Point(x: 446, y: 195),
                new Point(x: 418, y: 171),
                new Point(x: 409, y: 173),
                new Point(x: 401, y: 183),
                new Point(x: 391, y: 217),
                new Point(x: 381, y: 226),
                new Point(x: 355, y: 228),
                new Point(x: 343, y: 223),
                new Point(x: 322, y: 204),
                new Point(x: 303, y: 172),
                new Point(x: 303, y: 153),
                new Point(x: 306, y: 145),
                new Point(x: 315, y: 137),
                new Point(x: 326, y: 133),
                new Point(x: 359, y: 129),
                new Point(x: 373, y: 119),
                new Point(x: 373, y: 111),
                new Point(x: 366, y: 97),
                new Point(x: 328, y: 50),
                new Point(x: 318, y: 42),
                new Point(x: 295, y: 12),
                new Point(x: 289, y: 9),
                new Point(x: 284, y: 1),
            ],
            boundingBox: new BoundingBox(left: 170, right: 674, top: 301, bottom: 813),
        );

        $sides = $this->sideFinder->getSides($border);

        $this->assertEquals([
            new Point(x: 279, y: 0), // Corner
            new Point(x: 211, y: 61),
            new Point(x: 169, y: 94),
            new Point(x: 152, y: 112),
            new Point(x: 0, y: 238),
            new Point(x: 0, y: 241), // Corner
        ], $sides[0]);

        $this->assertEquals([
            new Point(x: 0, y: 241), // Corner
            new Point(x: 16, y: 250),
            new Point(x: 236, y: 509), // Corner
        ], $sides[1]);

        $this->assertEquals([
            new Point(x: 236, y: 509), // Corner
            new Point(x: 298, y: 456),
            new Point(x: 310, y: 449),
            new Point(x: 325, y: 449),
            new Point(x: 335, y: 463),
            new Point(x: 337, y: 486),
            new Point(x: 343, y: 501),
            new Point(x: 353, y: 509),
            new Point(x: 370, y: 512),
            new Point(x: 387, y: 505),
            new Point(x: 403, y: 493),
            new Point(x: 415, y: 479),
            new Point(x: 422, y: 456),
            new Point(x: 417, y: 443),
            new Point(x: 408, y: 434),
            new Point(x: 382, y: 427),
            new Point(x: 366, y: 418),
            new Point(x: 362, y: 410),
            new Point(x: 364, y: 399),
            new Point(x: 504, y: 266), // Corner
        ], $sides[2]);

        $this->assertEquals([
            new Point(x: 504, y: 266), // Corner
            new Point(x: 446, y: 195),
            new Point(x: 418, y: 171),
            new Point(x: 409, y: 173),
            new Point(x: 401, y: 183),
            new Point(x: 391, y: 217),
            new Point(x: 381, y: 226),
            new Point(x: 355, y: 228),
            new Point(x: 343, y: 223),
            new Point(x: 322, y: 204),
            new Point(x: 303, y: 172),
            new Point(x: 303, y: 153),
            new Point(x: 306, y: 145),
            new Point(x: 315, y: 137),
            new Point(x: 326, y: 133),
            new Point(x: 359, y: 129),
            new Point(x: 373, y: 119),
            new Point(x: 373, y: 111),
            new Point(x: 366, y: 97),
            new Point(x: 328, y: 50),
            new Point(x: 318, y: 42),
            new Point(x: 295, y: 12),
            new Point(x: 289, y: 9),
            new Point(x: 284, y: 1),
            new Point(x: 279, y: 0), // Corner
        ], $sides[3]);
    }

    public function testAllPieces(): void
    {
        $start = 2;
        $max = 501;

        $start = 10;
        $max = 10;

        for ($i = $start; $i <= $max; $i++) {
            try {
                $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece' . $i . '.jpg');
                $border = $this->borderFinder->findPieceBorder($image);
                $sides = $this->sideFinder->getSides($border);

                $color = imagecolorallocate($image, 0, 0, 0);
                foreach ($sides as $side) {
                    for ($x = $side[0]->getX() - 10 + $border->getBoundingBox()->getLeft(); $x < $side[0]->getX() + 10 + $border->getBoundingBox()->getLeft(); $x++) {
                        imagesetpixel($image,$x, $side[0]->getY() + $border->getBoundingBox()->getTop(), $color);
                    }
                    for ($y = $side[0]->getY() - 10 + $border->getBoundingBox()->getTop(); $y < $side[0]->getY() + 10 + $border->getBoundingBox()->getTop(); $y++) {
                        imagesetpixel($image,$side[0]->getX() + $border->getBoundingBox()->getLeft(), $y, $color);
                    }
                }

                imagepng($image, __DIR__ . '/../fixtures/pieces/piece' . $i . '_tested.jpg');

                echo 'piece' . $i . ' parsed.' . PHP_EOL;

            } catch (JigsawlutionerException $exception) {
                echo 'piece' . $i . ' could not be parsed: ' . $exception->getMessage() . PHP_EOL;
            }
        }

    }
}
