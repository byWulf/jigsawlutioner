<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\SideMetadata;

class CornerDistanceClassifier extends ModelBasedClassifier
{
    public function __construct(
        private float $width
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        return new CornerDistanceClassifier($metadata->getSideWidth());
    }

    public static function getModelPath(): string
    {
        return __DIR__ . '/../../resources/Model/cornerDistance.model';
    }

    /**
     * @param CornerDistanceClassifier $comparisonClassifier
     */
    public function getPredictionData(SideClassifierInterface $comparisonClassifier): array
    {
        return [abs($this->getWidth() - $comparisonClassifier->getWidth())];
    }

    /**
     * @param CornerDistanceClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        return max(0, 1 - (abs($this->width - $classifier->getWidth()) / 45)); // range 0 - 140
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function jsonSerialize(): float
    {
        return $this->width;
    }

    public function __toString(): string
    {
        return 'CornerDistance(' . round($this->width, 2) . ')';
    }
}
