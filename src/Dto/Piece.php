<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use JsonSerializable;

class Piece implements JsonSerializable
{
    /**
     * @param DerivativePoint[] $borderPoints
     * @param Side[]            $sides
     */
    public function __construct(
        private int $index,
        private array $borderPoints,
        private array $sides
    ) {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return DerivativePoint[]
     */
    public function getBorderPoints(): array
    {
        return $this->borderPoints;
    }

    /**
     * @return Side[]
     */
    public function getSides(): array
    {
        return $this->sides;
    }

    /**
     * @param Side[] $sides
     */
    public function setSides(array $sides): self
    {
        $this->sides = $sides;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'borderPoints' => array_map(
                fn (DerivativePoint $point): array => $point->jsonSerialize(),
                $this->borderPoints
            ),
            'sides' => array_map(
                fn (Side $side): array => $side->jsonSerialize(),
                $this->sides
            ),
        ];
    }

    public static function fromSerialized(string $serializedContent): self
    {
        return unserialize(
            $serializedContent,
            ['allowed_classes' => [
                DerivativePoint::class,
                Piece::class,
                Point::class,
                Side::class,
                SideMetadata::class,
                BigWidthClassifier::class,
                CornerDistanceClassifier::class,
                DepthClassifier::class,
                DirectionClassifier::class,
                SmallWidthClassifier::class,
            ]]
        );
    }
}
