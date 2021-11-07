<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Service\PointService;
use JsonSerializable;

class DirectionClassifier implements SideClassifierInterface
{
    public const NOP_STRAIGHT = 'straight';
    public const NOP_INSIDE = 'inside';
    public const NOP_OUTSIDE = 'outside';

    public function __construct(
        private string $direction
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        if (abs($metadata->getDepth()) < $metadata->getSideWidth() * 0.1) {
            return new DirectionClassifier(self::NOP_STRAIGHT);
        }

        return new DirectionClassifier($metadata->getDepth() < 0 ? self::NOP_INSIDE : self::NOP_OUTSIDE);
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * @param DirectionClassifier $classifier
     */
    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        if ($this->direction === self::NOP_STRAIGHT || $classifier->getDirection() === self::NOP_STRAIGHT) {
            return 0;
        }

        return $this->direction !== $classifier->getDirection() ? 1 : 0;
    }

    /**
     * @param DirectionClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        return $this->direction === $classifier->getDirection() ? 1 : 0;
    }

    public function jsonSerialize(): string
    {
        return $this->direction;
    }
}
