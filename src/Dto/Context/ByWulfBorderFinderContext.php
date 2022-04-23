<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto\Context;

use GdImage;

class ByWulfBorderFinderContext implements BorderFinderContextInterface
{
    /**
     * @param GdImage[] $transparentImages
     */
    public function __construct(
        private float $threshold,
        private array $transparentImages = [],
    ) {
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * @return GdImage[]
     */
    public function getTransparentImages(): array
    {
        return $this->transparentImages;
    }
}
