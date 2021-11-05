<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Service\PointService;

class DirectionClassifier implements SideClassifierInterface
{
    public const NOP_STRAIGHT = 'straight';
    public const NOP_INSIDE = 'inside';
    public const NOP_OUTSIDE = 'outside';

    private string $direction;

    private float $depth = 0;

    private int $deepestIndex = 0;

    public function __construct(Side $side)
    {
        $pointService = new PointService();
        $points = $side->getPoints();

        $this->deepestIndex = 0;
        foreach ($points as $index => $point) {
            if (abs($point->getY()) > abs($this->depth)) {
                $this->depth = $point->getY();
                $this->deepestIndex = $index;
            }
        }

        $width = $pointService->getDistanceBetweenPoints($points[0], $points[count($points) - 1]);

        $this->direction = abs($this->depth) < $width * 0.1 ? self::NOP_STRAIGHT : ($this->depth < 0 ? self::NOP_INSIDE : self::NOP_OUTSIDE);
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getDepth(): float
    {
        return $this->depth;
    }

    public function getDeepestIndex(): int
    {
        return $this->deepestIndex;
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
