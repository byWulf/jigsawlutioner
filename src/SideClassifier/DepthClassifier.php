<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;

class DepthClassifier implements SideClassifierInterface
{
    public function __construct(
        private string $direction,
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

    /**
     * @param DepthClassifier $classifier
     */
    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        $insideClassifier = $this->direction === DirectionClassifier::NOP_INSIDE ? $this : $classifier;
        $outsideClassifier = $this->direction === DirectionClassifier::NOP_OUTSIDE ? $this : $classifier;

        return max(0, 1 - (abs($outsideClassifier->getDepth() + $insideClassifier->getDepth() - 1.5) / 12));
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
}
