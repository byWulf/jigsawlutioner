<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\BorderFinder;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use PHPUnit\Framework\TestCase;

class ByWulfBorderFinderTest extends TestCase
{
    private ByWulfBorderFinder $borderFinder;

    protected function setUp(): void
    {
        $this->borderFinder = new ByWulfBorderFinder();
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorder(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../../resources/Fixtures/piece1_test.jpg');

        $points = [];
        for ($y = 256; $y < 256 + 251; ++$y) {
            $points[] = new Point(272, $y);
        }
        for ($x = 272; $x < 272 + 269; ++$x) {
            $points[] = new Point($x, 256 + 251);
        }
        for ($y = 256 + 251; $y > 256; --$y) {
            $points[] = new Point(272 + 269, $y);
        }
        for ($x = 272 + 269; $x > 272; --$x) {
            $points[] = new Point($x, 256);
        }

        $this->assertEquals($points, $this->borderFinder->findPieceBorder(image: $image));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderReal(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../../resources/Fixtures/piece2.jpg');

        $this->assertNotEmpty($this->borderFinder->findPieceBorder(image: $image));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderWithThinLineOnBorder(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../../resources/Fixtures/piece2modified.jpg');

        $this->assertNotEmpty($this->borderFinder->findPieceBorder(image: $image));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderCutOff(): void
    {
        $this->expectExceptionMessage('Piece is cut off');

        $image = imagecreatefromjpeg(__DIR__ . '/../../../resources/Fixtures/piece2modified_cutoff.jpg');

        $this->borderFinder->findPieceBorder(image: $image);
    }
}
