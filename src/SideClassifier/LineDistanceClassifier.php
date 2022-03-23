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
        if ($metadata->getSide()->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            throw new SideClassifierException('Not available on straight sides.');
        }

        $averageDistanceSum = 0;
        $minDistance = null;
        $maxDistance = null;
        $countedPointsCount = 0;

        foreach (self::getPointsToLookAt($metadata, 0.2) as $point) {
            ++$countedPointsCount;
            $averageDistanceSum += $point->getY();

            if ($minDistance === null || $point->getY() < $minDistance) {
                $minDistance = $point->getY();
            }

            if ($maxDistance === null || $point->getY() > $maxDistance) {
                $maxDistance = $point->getY();
            }
        }

        return new LineDistanceClassifier(
            $metadata->getSide()->getDirection(),
            $countedPointsCount > 0 ? $averageDistanceSum / $countedPointsCount : 0,
            $minDistance ?? 0.0,
            $maxDistance ?? 0.0
        );
    }

    /**
     * @throws SideClassifierException
     *
     * @return iterable<Point>
     */
    private static function getPointsToLookAt(SideMetadata $metadata, float $skipPercentage): iterable
    {
        $smallestWidthIndex = self::getSmallestWidthIndex($metadata);
        $oppositeSmallestWidthIndex = self::getOppositeSmallestWidthIndex($metadata, $smallestWidthIndex);

        $pointsCount = count($metadata->getSide()->getPoints());
        $leftPointCount = $smallestWidthIndex + 1;
        $rightPointCount = $pointsCount - $oppositeSmallestWidthIndex;

        for ($i = round($leftPointCount * $skipPercentage); $i < $pointsCount - round($rightPointCount * $skipPercentage); ++$i) {
            if ($i >= round($leftPointCount * (1 - $skipPercentage)) && $i <= $oppositeSmallestWidthIndex + round($rightPointCount * $skipPercentage)) {
                continue;
            }

            yield $metadata->getSide()->getPoints()[$i];
        }
    }

    private static function getSmallestWidthIndex(SideMetadata $metadata): int
    {
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

        return $smallestWidthIndex;
    }

    private static function getOppositeSmallestWidthIndex(SideMetadata $metadata, int $smallestWidthIndex): int
    {
        $isInside = $metadata->getSide()->getDirection() === DirectionClassifier::NOP_INSIDE;

        $oppositeSmallestWidthIndex = $metadata->getDeepestIndex();
        $pointsCount = count($metadata->getSide()->getPoints());
        for ($i = $metadata->getDeepestIndex(); $i < $pointsCount; ++$i) {
            if (
                ($isInside && $metadata->getSide()->getPoints()[$i]->getY() < $metadata->getSide()->getPoints()[$smallestWidthIndex]->getY()) ||
                (!$isInside && $metadata->getSide()->getPoints()[$i]->getY() > $metadata->getSide()->getPoints()[$smallestWidthIndex]->getY())
            ) {
                $oppositeSmallestWidthIndex = $i;
            }
        }

        return $oppositeSmallestWidthIndex;
    }

    public static function getModelPath(): string
    {
        return __DIR__ . '/../../resources/Model/lineDistance.model';
    }

    /**
     * @param LineDistanceClassifier $comparisonClassifier
     *
     * @return array{float, float}
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
