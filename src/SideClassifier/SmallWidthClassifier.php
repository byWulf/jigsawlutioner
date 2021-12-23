<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Util\TimeTrackerTrait;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

class SmallWidthClassifier implements SideClassifierInterface
{
    use TimeTrackerTrait;

    private static ?PersistentModel $estimator = null;

    public function __construct(
        private int $direction,
        private float $width,
        private Point $centerPoint
    ) {
    }

    public static function fromMetadata(SideMetadata $metadata): self
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $metadata->getSide()->getClassifier(DirectionClassifier::class);
        if ($directionClassifier->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
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
            $directionClassifier->getDirection(),
            $pointWidths[$smallestWidthIndex],
            new Point(
                $points[$smallestWidthIndex]->getX() + $pointWidths[$smallestWidthIndex] / 2,
                $points[$smallestWidthIndex]->getY()
            )
        );
    }

    public function getPredictionData(SmallWidthClassifier $comparisonClassifier): array
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
    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        if (self::$estimator === null) {
            self::$estimator = PersistentModel::load(new Filesystem(__DIR__ . '/../../resources/Model/bigNopMatcher.model'));
        }

        $probability = $this->withTimeTracking(function () use ($classifier) {
            $data = $this->getPredictionData($classifier);

            return self::$estimator->predict(Unlabeled::quick([$data]))[0];
        });

        return $probability;
    }

    /**
     * @param SmallWidthClassifier $classifier
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        $insideClassifier = $this->direction === DirectionClassifier::NOP_INSIDE ? $this : $classifier;
        $outsideClassifier = $this->direction === DirectionClassifier::NOP_OUTSIDE ? $this : $classifier;

        $xDiff = $insideClassifier->getCenterPoint()->getX() - $outsideClassifier->getCenterPoint()->getX();
        $yDiff = $insideClassifier->getCenterPoint()->getY() - $insideClassifier->getCenterPoint()->getY();
        $widthDiff = $insideClassifier->getWidth() - $outsideClassifier->getWidth();

        return 1 - ((min(1, abs($xDiff) / 10) + min(1, abs($yDiff) / 10) + min(1, abs($widthDiff) / 10)) / 3);
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
}
