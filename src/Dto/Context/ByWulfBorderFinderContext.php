<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto\Context;

use GdImage;

class ByWulfBorderFinderContext implements BorderFinderContextInterface
{
    public function __construct(
        private float $threshold,
        private ?GdImage $transparentImage = null,
        private ?GdImage $smallTransparentImage = null,
    ) {
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function getTransparentImage(): ?GdImage
    {
        return $this->transparentImage;
    }

    public function setTransparentImage(?GdImage $transparentImage): ByWulfBorderFinderContext
    {
        $this->transparentImage = $transparentImage;

        return $this;
    }

    public function getSmallTransparentImage(): ?GdImage
    {
        return $this->smallTransparentImage;
    }

    public function setSmallTransparentImage(?GdImage $smallTransparentImage): ByWulfBorderFinderContext
    {
        $this->smallTransparentImage = $smallTransparentImage;

        return $this;
    }
}
