<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use JsonSerializable;

class ReducedPiece implements JsonSerializable
{
    /**
     * @param ReducedSide[] $sides
     */
    public function __construct(
        private int $index,
        private array $sides,
        private readonly int $imageWidth,
        private readonly int $imageHeight
    ) {
    }

    public static function fromPiece(Piece $piece): self
    {
        return new self(
            $piece->getIndex(),
            array_map(fn (Side $side): ReducedSide => ReducedSide::fromSide($side), $piece->getSides()),
            $piece->getImageWidth(),
            $piece->getImageHeight(),
        );
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return ReducedSide[]
     */
    public function getSides(): array
    {
        return $this->sides;
    }

    public function getSide(int $sideIndex): ReducedSide
    {
        while ($sideIndex < 0) {
            $sideIndex += 4;
        }

        return $this->sides[$sideIndex % 4];
    }

    public function getImageWidth(): int
    {
        return $this->imageWidth;
    }

    public function getImageHeight(): int
    {
        return $this->imageHeight;
    }

    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'sides' => array_map(
                static fn (ReducedSide $side): array => $side->jsonSerialize(),
                $this->sides
            ),
            'imageWidth' => $this->imageWidth,
            'imageHeight' => $this->imageHeight,
        ];
    }
}
