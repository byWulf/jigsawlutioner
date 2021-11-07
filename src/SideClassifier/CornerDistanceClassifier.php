<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\SideMetadata;

class CornerDistanceClassifier implements SideClassifierInterface
{
    public function __construct(
        private float $width
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        return new CornerDistanceClassifier($metadata->getSideWidth());
    }

    /**
     * @param CornerDistanceClassifier $classifier
     */
    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        return max(0, 1 - (abs($this->width - $classifier->getWidth()) / 20));
    }

    /**
     * @param CornerDistanceClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        return max(0, 1 - (abs($this->width - $classifier->getWidth()) / 20));
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function jsonSerialize(): float
    {
        return $this->width;
    }
}
