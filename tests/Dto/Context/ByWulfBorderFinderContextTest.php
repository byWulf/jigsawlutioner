<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto\Context;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\Context\ByWulfBorderFinderContext
 */
class ByWulfBorderFinderContextTest extends TestCase
{
    public function testGetters(): void
    {
        $threshold = 0.65;
        $transparentImage = imagecreatetruecolor(10, 10);
        $smallTransparentImage = imagecreatetruecolor(1, 1);

        $context = new ByWulfBorderFinderContext($threshold, $transparentImage, $smallTransparentImage);

        $this->assertEquals($threshold, $context->getThreshold());
        $this->assertEquals($transparentImage, $context->getTransparentImage());
        $this->assertEquals($smallTransparentImage, $context->getSmallTransparentImage());
    }
}
