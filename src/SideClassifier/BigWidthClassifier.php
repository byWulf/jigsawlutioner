<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;

class BigWidthClassifier extends ModelBasedClassifier
{
    public function __construct(
        private int $direction,
        private float $width,
        private Point $centerPoint
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        if ($metadata->getSide()->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            throw new SideClassifierException('Not available on straight sides.');
        }

        $points = $metadata->getSide()->getPoints();

        $pointWidths = $metadata->getPointWidths();
        for ($i = $metadata->getDeepestIndex(); $i >= 0; --$i) {
            // Search for the first width, that gets smaller than the width before
            if (isset($pointWidths[$i + 1]) && $pointWidths[$i] < $pointWidths[$i + 1]) {
                return new BigWidthClassifier(
                    $metadata->getSide()->getDirection(),
                    $pointWidths[$i + 1],
                    new Point(
                        $points[$i + 1]->getX() + $pointWidths[$i + 1] / 2,
                        $points[$i + 1]->getY()
                    )
                );
            }
        }

        throw new SideClassifierException('Couldn\'t determine biggest width of nop.');
    }

    public static function getModelPath(): string
    {
        return __DIR__ . '/../../resources/Model/bigNop.model';
    }

    /**
     * @param BigWidthClassifier $comparisonClassifier
     */
    public function getPredictionData(SideClassifierInterface $comparisonClassifier): array
    {
        $insideClassifier = $this->direction === DirectionClassifier::NOP_INSIDE ? $this : $comparisonClassifier;
        $outsideClassifier = $this->direction === DirectionClassifier::NOP_OUTSIDE ? $this : $comparisonClassifier;

        $xDiff = -$insideClassifier->getCenterPoint()->getX() - $outsideClassifier->getCenterPoint()->getX();
        $yDiff = $outsideClassifier->getCenterPoint()->getY() + $insideClassifier->getCenterPoint()->getY();
        $widthDiff = $insideClassifier->getWidth() - $outsideClassifier->getWidth();

        return [$xDiff, $yDiff, $widthDiff];
    }

    /**
     * @param BigWidthClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        $xDiff = abs($this->getCenterPoint()->getX() - $classifier->getCenterPoint()->getX()); // range 0 - 150
        $yDiff = abs($this->getCenterPoint()->getY() - $classifier->getCenterPoint()->getY()); // range 0 - 110
        $widthDiff = abs($this->getWidth() - $classifier->getWidth()); // range 0 - 100

        $xRating = $xDiff > 50 ? 0 : 1 - ($xDiff / 50);
        $yRating = $yDiff > 35 ? 0 : 1 - ($yDiff / 35);
        $widthRating = $widthDiff > 30 ? 0 : 1 - ($widthDiff / 30);

        return ($xRating + $yRating + $widthRating) / 3;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getCenterPoint(): Point
    {
        return $this->centerPoint;
    }

    public function jsonSerialize(): array
    {
        return [
            'direction' => $this->direction,
            'width' => $this->width,
            'centerPoint' => $this->centerPoint->jsonSerialize(),
        ];
    }

    public function __toString(): string
    {
        return 'BigWidth(w: ' . round($this->width, 2) . ', cx: ' . round($this->centerPoint->getX(), 2) . ', cy: ' . round($this->centerPoint->getY(), 2) . ')';
    }
}
