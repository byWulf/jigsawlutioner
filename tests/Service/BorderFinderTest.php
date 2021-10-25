<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service;

use Bywulf\Jigsawlutioner\Dto\Border;
use Bywulf\Jigsawlutioner\Dto\BoundingBox;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Service\BorderFinder;
use Bywulf\Jigsawlutioner\Service\PathService;
use PHPUnit\Framework\TestCase;

class BorderFinderTest extends TestCase
{
    private BorderFinder $borderFinder;

    protected function setUp(): void
    {
        $this->borderFinder = new BorderFinder(new PathService());
    }

    public function testFindPieceBorder(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece1_test.jpg');

        $expected = new Border(
            [
                new Point(0, 0),
                new Point(0, 251),
                new Point(269, 251),
                new Point(269, 0),
                new Point(0, 0),
            ],
            new BoundingBox(272, 541, 256, 507)
        );

        $this->assertEquals($expected, $this->borderFinder->findPieceBorder(image: $image));
    }

    public function testFindPieceBorderReal(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece2.jpg');

        $result = $this->borderFinder->findPieceBorder(image: $image);

        $this->assertEquals($result->getBoundingBox(), new BoundingBox(170, 674, 301, 813));
        $this->assertNotEmpty($result->getPoints());
    }

    public function testFindPieceBorderWithThinLineOnBorder(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece2modified.jpg');

        $result = $this->borderFinder->findPieceBorder(image: $image);

        $this->assertEquals($result->getBoundingBox(), new BoundingBox(170, 674, 301, 813));
        $this->assertNotEmpty($result->getPoints());
    }

    public function testFindPieceBorderCutOff(): void
    {
        $this->expectExceptionMessage('Piece is cut off');

        $image = imagecreatefromjpeg(__DIR__ . '/../fixtures/pieces/piece2modified_cutoff.jpg');

        $this->borderFinder->findPieceBorder(image: $image);
    }
}
