<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\BorderFinder;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder;
use Bywulf\Jigsawlutioner\Tests\Helper\WrongBorderFinderContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder
 * @covers \Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder\PixelManipulator
 * @covers \Bywulf\Jigsawlutioner\Service\BorderFinder\ByWulfBorderFinder\PointParser
 */
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
        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece1_test.jpg');

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

        $this->assertEquals($points, $this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(0.95)));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderTransparent(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece1_test.jpg');
        $imageColor = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece1_test_color.jpg');
        $imageColorSmall = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece1_test_color_small.jpg');

        $this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(
            threshold: 0.95,
            transparentImages: [$imageColor, $imageColorSmall],
        ));

        ob_start();
        imagepng($imageColor);
        $content = ob_get_clean();

        $expectedImage = imagecreatefrompng(__DIR__ . '/../../fixtures/piece1_test_color_expected.jpg');
        ob_start();
        imagepng($expectedImage);
        $expectedContent = ob_get_clean();

        $this->assertEquals($expectedContent, $content);

        ob_start();
        imagepng($imageColorSmall);
        $contentSmall = ob_get_clean();

        $expectedImage = imagecreatefrompng(__DIR__ . '/../../fixtures/piece1_test_color_small_expected.jpg');
        ob_start();
        imagepng($expectedImage);
        $expectedContentSmall = ob_get_clean();

        $this->assertEquals($expectedContentSmall, $contentSmall);
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderReal(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece2.jpg');

        $this->assertNotEmpty($this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(0.95)));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderWithThinLineOnBorder(): void
    {
        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece2modified.jpg');

        $this->assertNotEmpty($this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(0.95)));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderCutOff(): void
    {
        $this->expectExceptionMessage('Piece is cut off');

        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece2modified_cutoff.jpg');

        $this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(0.95));
    }

    /**
     * @throws BorderParsingException
     */
    public function testFindPieceBorderCutOffVertical(): void
    {
        $this->expectExceptionMessage('Piece is cut off');

        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece2modified_cutoff_vertical.jpg');

        $this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(0.95));
    }

    public function testWithWrongContext(): void
    {
        $this->expectExceptionMessage('Expected context of type ' . ByWulfBorderFinderContext::class . ', got ' . WrongBorderFinderContext::class);

        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece2modified_cutoff.jpg');

        $this->borderFinder->findPieceBorder(image: $image, context: new WrongBorderFinderContext());
    }

    public function testFindPieceBorderEmpty(): void
    {
        $this->expectExceptionMessage('No area found');

        $image = imagecreatefromjpeg(__DIR__ . '/../../fixtures/piece2modified_empty.jpg');

        $this->borderFinder->findPieceBorder(image: $image, context: new ByWulfBorderFinderContext(0.95));
    }
}
