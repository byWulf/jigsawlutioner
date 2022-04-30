<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use InvalidArgumentException;

class DirectionClassifier implements SideClassifierInterface
{
    public const NOP_STRAIGHT = 0;

    public const NOP_INSIDE = -1;

    public const NOP_OUTSIDE = 1;

    public function __construct(
        private int $direction
    ) {
        if (!in_array($this->direction, [self::NOP_STRAIGHT, self::NOP_INSIDE, self::NOP_OUTSIDE], true)) {
            throw new InvalidArgumentException('Invalid direction "' . $this->direction . '" given.');
        }
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        if (abs($metadata->getDepth()) < $metadata->getSideWidth() * 0.1) {
            return new DirectionClassifier(self::NOP_STRAIGHT);
        }

        return new DirectionClassifier($metadata->getDepth() < 0 ? self::NOP_INSIDE : self::NOP_OUTSIDE);
    }

    public function getDirection(): int
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

    public function jsonSerialize(): int
    {
        return $this->direction;
    }

    public function __toString(): string
    {
        $directionString = match ($this->direction) {
            self::NOP_INSIDE => 'inside',
            self::NOP_OUTSIDE => 'outside',
            self::NOP_STRAIGHT => 'straight',
            default => 'unknown',
        };

        return 'Direction(' . $directionString . ')';
    }
}
