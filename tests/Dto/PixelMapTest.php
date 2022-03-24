<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\PixelMap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\PixelMap
 */
class PixelMapTest extends TestCase
{
    public function testCreateFromImage(): void
    {
        $image = imagecreatefrompng(__DIR__ . '/../fixtures/pixelmap.png');
        $pixelMap = PixelMap::createFromImage($image);

        $this->assertEquals($image, $pixelMap->getImage());
        $this->assertEquals(10, $pixelMap->getWidth());
        $this->assertEquals(8, $pixelMap->getHeight());

        $wh = 16777215;
        $bk = 0;
        $rd = 16711680;
        $gr = 65280;
        $bl = 255;

        $this->assertEquals([
            0 => [0 => $wh, 1 => $wh, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $wh, 6 => $wh, 7 => $wh, 8 => $wh, 9 => $wh],
            1 => [0 => $wh, 1 => $rd, 2 => $gr, 3 => $bl, 4 => $wh, 5 => $bk, 6 => $bk, 7 => $bk, 8 => $bk, 9 => $wh],
            2 => [0 => $wh, 1 => $wh, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $bk, 6 => $wh, 7 => $wh, 8 => $bk, 9 => $wh],
            3 => [0 => $wh, 1 => $bk, 2 => $bk, 3 => $bk, 4 => $bk, 5 => $bk, 6 => $wh, 7 => $wh, 8 => $bk, 9 => $wh],
            4 => [0 => $wh, 1 => $bk, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $wh, 6 => $wh, 7 => $wh, 8 => $bk, 9 => $wh],
            5 => [0 => $wh, 1 => $bk, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $wh, 6 => $wh, 7 => $wh, 8 => $bk, 9 => $wh],
            6 => [0 => $wh, 1 => $bk, 2 => $bk, 3 => $bk, 4 => $bk, 5 => $bk, 6 => $bk, 7 => $bk, 8 => $bk, 9 => $wh],
            7 => [0 => $wh, 1 => $wh, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $wh, 6 => $wh, 7 => $wh, 8 => $wh, 9 => $wh],
        ], $pixelMap->getPixels());
    }

    public function testGetColor(): void
    {
        $image = imagecreatefrompng(__DIR__ . '/../fixtures/pixelmap.png');
        $pixelMap = PixelMap::createFromImage($image);

        $rd = 16711680;
        $gr = 65280;

        $this->assertEquals($rd, $pixelMap->getColor(1, 1));

        $pixelMap->setColor(1, 1, $gr);

        $this->assertEquals($gr, $pixelMap->getColor(1, 1));

        // Setting an invalid position should not cause any problems
        $pixelMap->setColor(100, 1, $gr);

        $this->assertEquals($gr, $pixelMap->getColor(1, 1));
    }

    public function testApplyToImage(): void
    {
        $image = imagecreatefrompng(__DIR__ . '/../fixtures/pixelmap.png');
        $pixelMap = PixelMap::createFromImage($image);

        $rd = 16711680;
        $gr = 65280;

        $pixelMap->setColor(1, 1, $gr);
        $pixelMap->scanFill(6, 4, $rd);

        $pixelMap->applyToImage();

        ob_start();
        imagepng($image);
        $content = ob_get_clean();

        $expectedImage = imagecreatefrompng(__DIR__ . '/../fixtures/pixelmap_expected.png');
        ob_start();
        imagepng($expectedImage);
        $expectedContent = ob_get_clean();

        $this->assertEquals($expectedContent, $content);
    }

    public function testScanFill(): void
    {
        $image = imagecreatefrompng(__DIR__ . '/../fixtures/pixelmap.png');
        $pixelMap = PixelMap::createFromImage($image);

        $wh = 16777215;
        $bk = 0;
        $rd = 16711680;
        $gr = 65280;
        $bl = 255;

        $pixelMap->scanFill(6, 4, $rd);

        $this->assertEquals([
            0 => [0 => $wh, 1 => $wh, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $wh, 6 => $wh, 7 => $wh, 8 => $wh, 9 => $wh],
            1 => [0 => $wh, 1 => $rd, 2 => $gr, 3 => $bl, 4 => $wh, 5 => $bk, 6 => $bk, 7 => $bk, 8 => $bk, 9 => $wh],
            2 => [0 => $wh, 1 => $wh, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $bk, 6 => $rd, 7 => $rd, 8 => $bk, 9 => $wh],
            3 => [0 => $wh, 1 => $bk, 2 => $bk, 3 => $bk, 4 => $bk, 5 => $bk, 6 => $rd, 7 => $rd, 8 => $bk, 9 => $wh],
            4 => [0 => $wh, 1 => $bk, 2 => $rd, 3 => $rd, 4 => $rd, 5 => $rd, 6 => $rd, 7 => $rd, 8 => $bk, 9 => $wh],
            5 => [0 => $wh, 1 => $bk, 2 => $rd, 3 => $rd, 4 => $rd, 5 => $rd, 6 => $rd, 7 => $rd, 8 => $bk, 9 => $wh],
            6 => [0 => $wh, 1 => $bk, 2 => $bk, 3 => $bk, 4 => $bk, 5 => $bk, 6 => $bk, 7 => $bk, 8 => $bk, 9 => $wh],
            7 => [0 => $wh, 1 => $wh, 2 => $wh, 3 => $wh, 4 => $wh, 5 => $wh, 6 => $wh, 7 => $wh, 8 => $wh, 9 => $wh],
        ], $pixelMap->getPixels());
    }
}
