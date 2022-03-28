<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\LineDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use InvalidArgumentException;
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
        private array $sides,
        private int $imageWidth,
        private int $imageHeight
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

    public function getSide(int $sideIndex): Side
    {
        while ($sideIndex < 0) {
            $sideIndex += 4;
        }

        return $this->sides[$sideIndex % 4];
    }

    /**
     * @param Side[] $sides
     */
    public function setSides(array $sides): self
    {
        $this->sides = $sides;

        return $this;
    }

    public function getImageWidth(): int
    {
        return $this->imageWidth;
    }

    public function getImageHeight(): int
    {
        return $this->imageHeight;
    }

    public function reduceData(): self
    {
        $this->borderPoints = [];

        foreach ($this->sides as $side) {
            $side->setPoints([]);
            $side->setUnrotatedPoints([]);
        }

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'borderPoints' => array_map(
                static fn (DerivativePoint $point): array => $point->jsonSerialize(),
                $this->borderPoints
            ),
            'sides' => array_map(
                static fn (Side $side): array => $side->jsonSerialize(),
                $this->sides
            ),
            'imageWidth' => $this->imageWidth,
            'imageHeight' => $this->imageHeight,
        ];
    }

    public static function fromSerialized(string $serializedContent): self
    {
        $piece = unserialize(
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
                LineDistanceClassifier::class,
            ]]
        );

        if (!$piece instanceof Piece) {
            throw new InvalidArgumentException('Given serialized object was not a Piece.');
        }

        return $piece;
    }
}
