<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Dto;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Bywulf\Jigsawlutioner\Dto\SideMetadata
 */
class SideMetadataTest extends TestCase
{
    public function testSideMetadata(): void
    {
        $side = new Side([new Point(1, 2)], new Point(3, 4), new Point(5, 6));
        $sideMetadata = new SideMetadata($side, 10.5, 15.5, 42, [0.1, 0.2, 0.3, 0.4]);

        $this->assertEquals($side, $sideMetadata->getSide());
        $this->assertEquals(10.5, $sideMetadata->getSideWidth());
        $this->assertEquals(15.5, $sideMetadata->getDepth());
        $this->assertEquals(42, $sideMetadata->getDeepestIndex());
        $this->assertEquals([0.1, 0.2, 0.3, 0.4], $sideMetadata->getPointWidths());
    }
}
