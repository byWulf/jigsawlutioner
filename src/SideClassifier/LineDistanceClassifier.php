<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Stringable;

class LineDistanceClassifier extends ModelBasedClassifier implements Stringable
{
    public function __construct(
        private int $direction,
        private float $averageLineDistance,
        private float $minLineDistance,
        private float $maxLineDistance
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $metadata->getSide()->getClassifier(DirectionClassifier::class);
        if ($directionClassifier->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            throw new SideClassifierException('Not available on straight sides.');
        }
        $isInside = $directionClassifier->getDirection() === DirectionClassifier::NOP_INSIDE;

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

        $oppositeSmallestWidthIndex = $metadata->getDeepestIndex();
        $pointsCount = count($metadata->getSide()->getPoints());
        for ($i = $metadata->getDeepestIndex(); $i < $pointsCount; $i++) {
            if (
                ($isInside && $metadata->getSide()->getPoints()[$i]->getY() < $metadata->getSide()->getPoints()[$smallestWidthIndex]->getY()) ||
                (!$isInside && $metadata->getSide()->getPoints()[$i]->getY() > $metadata->getSide()->getPoints()[$smallestWidthIndex]->getY())
            ) {
                $oppositeSmallestWidthIndex = $i;
            }
        }

        $averageDistanceSum = 0;
        $minDistance = null;
        $maxDistance = null;
        $countedPointsCount = 0;
        $skipPercentage = 0.2;

        $leftPointCount = $smallestWidthIndex + 1;
        for ($i = round($leftPointCount * $skipPercentage); $i < round($leftPointCount * (1 - $skipPercentage)); $i++) {
            $y = $metadata->getSide()->getPoints()[$i]->getY();
            $countedPointsCount++;
            $averageDistanceSum += $y;
            if ($minDistance === null || $y < $minDistance) {
                $minDistance = $y;
            }
            if ($maxDistance === null || $y > $maxDistance) {
                $maxDistance = $y;
            }
        }

        $rightPointCount = $pointsCount - $oppositeSmallestWidthIndex + 1;
        for ($i = round($rightPointCount * $skipPercentage) + $oppositeSmallestWidthIndex; $i < $pointsCount - round($rightPointCount * $skipPercentage); $i++) {
            $y = $metadata->getSide()->getPoints()[$i]->getY();
            $countedPointsCount++;
            $averageDistanceSum += $y;
            if ($minDistance === null || $y < $minDistance) {
                $minDistance = $y;
            }
            if ($maxDistance === null || $y > $maxDistance) {
                $maxDistance = $y;
            }
        }

        return new LineDistanceClassifier(
            $directionClassifier->getDirection(),
            $countedPointsCount > 0 ? $averageDistanceSum / $countedPointsCount : 0,
            $minDistance ?? 0.0,
            $maxDistance ?? 0.0
        );
    }

    public static function getModelPath(): string
    {
        return __DIR__ . '/../../resources/Model/lineDistance.model';
    }

    /**
     * @param LineDistanceClassifier $comparisonClassifier
     */
    public function getPredictionData(SideClassifierInterface $comparisonClassifier): array
    {
        $insideClassifier = $this->direction === DirectionClassifier::NOP_INSIDE ? $this : $comparisonClassifier;
        $outsideClassifier = $this->direction === DirectionClassifier::NOP_OUTSIDE ? $this : $comparisonClassifier;

        return [
            $insideClassifier->getAverageLineDistance() + $outsideClassifier->getAverageLineDistance(),
            $insideClassifier->getMaxLineDistance() + $outsideClassifier->getMinLineDistance(),
        ];
    }

    /**
     * @param LineDistanceClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        return 0;
    }

    public function getDirection(): int
    {
        return $this->direction;
    }

    public function getAverageLineDistance(): float
    {
        return $this->averageLineDistance;
    }

    public function getMinLineDistance(): float
    {
        return $this->minLineDistance;
    }

    public function getMaxLineDistance(): float
    {
        return $this->maxLineDistance;
    }

    public function jsonSerialize(): array
    {
        return [
            'direction' => $this->direction,
            'averageLineDistance' => $this->averageLineDistance,
            'minLineDistance' => $this->minLineDistance,
            'maxLineDistance' => $this->maxLineDistance,
        ];
    }

    public function __toString(): string
    {
        return 'LineDistance(avg: ' . round($this->averageLineDistance, 2) . ', min: ' . round($this->minLineDistance, 2) . ', max: ' . round($this->maxLineDistance, 2) . ')';
    }
}
