<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;

class SmallWidthClassifier extends ModelBasedClassifier
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
        $gettingBigger = true;
        $smallestWidthIndex = 0;
        for ($i = $metadata->getDeepestIndex(); $i >= 0; --$i) {
            if ($gettingBigger && isset($pointWidths[$i + 1]) && $pointWidths[$i] < $pointWidths[$i + 1]) {
                $gettingBigger = false;
                $smallestWidthIndex = $i + 1;
            }

            if (!$gettingBigger && $pointWidths[$i] < $pointWidths[$smallestWidthIndex]) {
                $smallestWidthIndex = $i;
            }
        }

        if ($smallestWidthIndex === 0) {
            throw new SideClassifierException('Couldn\'t determine smallest width of nop.');
        }

        return new SmallWidthClassifier(
            $metadata->getSide()->getDirection(),
            $pointWidths[$smallestWidthIndex],
            new Point(
                $points[$smallestWidthIndex]->getX() + $pointWidths[$smallestWidthIndex] / 2,
                $points[$smallestWidthIndex]->getY()
            )
        );
    }

    public static function getModelPath(): string
    {
        return __DIR__ . '/../../resources/Model/smallNop.model';
    }

    /**
     * @param SmallWidthClassifier $comparisonClassifier
     *
     * @return array{float, float, float}
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
     * @param SmallWidthClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        $xDiff = abs($this->getCenterPoint()->getX() - $classifier->getCenterPoint()->getX()); // range 0 - 140
        $yDiff = abs($this->getCenterPoint()->getY() - $classifier->getCenterPoint()->getY()); // range 0 - 120
        $widthDiff = abs($this->getWidth() - $classifier->getWidth()); // range 0 - 140

        $xRating = $xDiff > 45 ? 0 : 1 - ($xDiff / 45);
        $yRating = $yDiff > 40 ? 0 : 1 - ($yDiff / 40);
        $widthRating = $widthDiff > 45 ? 0 : 1 - ($widthDiff / 45);

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
        return 'SmallWidth(w: ' . round($this->width, 2) . ', cx: ' . round($this->centerPoint->getX(), 2) . ', cy: ' . round($this->centerPoint->getY(), 2) . ')';
    }
}
