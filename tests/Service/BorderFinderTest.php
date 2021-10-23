<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Border;
use Bywulf\Jigsawlutioner\Dto\BoundingBox;
use Bywulf\Jigsawlutioner\Service\BorderFinder;
use Imagick;
use PHPUnit\Framework\TestCase;

class BorderFinderTest extends TestCase
{
    private BorderFinder $borderFinder;

    protected function setUp(): void
    {
        $this->borderFinder = new BorderFinder();
    }

    public function testFindPieceBorder()
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece2.jpg');

        $expected = new Border(
            [],
            new BoundingBox(170, 674, 301, 813)
        );

        $this->assertEquals($expected, (new BorderFinder())->findPieceBorder(image: $image));
    }

    public function testFindPieceBorderWithThinLineOnBorder()
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece2modified.jpg');

        $expected = new Border(
            [],
            new BoundingBox(170, 674, 301, 813)
        );

        $this->assertEquals($expected, (new BorderFinder())->findPieceBorder(image: $image));
    }

    public function testFindPieceBorderCutOff()
    {
        $this->expectExceptionMessage('Piece is cut off');

        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece2modified_cutoff.jpg');

        (new BorderFinder())->findPieceBorder(image: $image);
    }
}
