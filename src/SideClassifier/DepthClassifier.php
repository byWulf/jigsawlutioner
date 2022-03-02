<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Stringable;

class DepthClassifier extends ModelBasedClassifier implements Stringable
{
    public function __construct(
        private int $direction,
        private float $depth
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $metadata->getSide()->getClassifier(DirectionClassifier::class);
        if ($directionClassifier->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            throw new SideClassifierException('Not available on straight sides.');
        }

        return new DepthClassifier($directionClassifier->getDirection(), $metadata->getDepth());
    }

    public static function getModelPath(): string
    {
        return __DIR__ . '/../../resources/Model/depth.model';
    }

    /**
     * @param DepthClassifier $comparisonClassifier
     */
    public function getPredictionData(SideClassifierInterface $comparisonClassifier): array
    {
        $insideClassifier = $this->direction === DirectionClassifier::NOP_INSIDE ? $this : $comparisonClassifier;
        $outsideClassifier = $this->direction === DirectionClassifier::NOP_OUTSIDE ? $this : $comparisonClassifier;

        return [$insideClassifier->getDepth() + $outsideClassifier->getDepth()];
    }

    /**
     * @param DepthClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        return max(0, 1 - (abs($this->depth - $classifier->getDepth()) / 12));
    }

    public function getDepth(): float
    {
        return $this->depth;
    }

    public function jsonSerialize(): float
    {
        return $this->depth;
    }

    public function __toString(): string
    {
        return 'Depth(' . round($this->depth, 2) . ')';
    }
}
