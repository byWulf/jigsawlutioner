<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Estimator;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

class SmallWidthClassifier implements SideClassifierInterface
{
    private static ?PersistentModel $estimator = null;

    private int $smallestWidthIndex;

    private float $width = 0;

    private Point $centerPoint;

    public function __construct(private Side $side)
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $side->getClassifier(DirectionClassifier::class);
        if ($directionClassifier->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            throw new SideClassifierException('Not available on straight sides.');
        }

        /** @var BigWidthClassifier $bigWidthClassifier */
        $bigWidthClassifier = $side->getClassifier(BigWidthClassifier::class);

        $points = $side->getPoints();
        $pointWidths = $bigWidthClassifier->getPointWidths();
        $pointWidthsCount = count($pointWidths);

        $smallestWidthIndex = null;
        for ($i = 0; $i < $pointWidthsCount; ++$i) {
            if ($smallestWidthIndex === null || $pointWidths[$i] < $this->width) {
                $this->width = $pointWidths[$i];
                $smallestWidthIndex = $i;
            }

            if ($i >= $bigWidthClassifier->getBiggestWidthIndex()) {
                break;
            }
        }

        if ($smallestWidthIndex === null) {
            throw new SideClassifierException('Couldn\'t determine smallest width of nop.');
        }
        $this->smallestWidthIndex = $smallestWidthIndex;

        $this->centerPoint = new Point(
            $points[$smallestWidthIndex]->getX() + $this->width / 2,
            $points[$smallestWidthIndex]->getY()
        );
    }

    /**
     * @param SmallWidthClassifier $classifier
     *
     * @throws SideClassifierException
     */
    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $this->side->getClassifier(DirectionClassifier::class);
        $direction = $directionClassifier->getDirection();

        $insideClassifier = $direction === DirectionClassifier::NOP_INSIDE ? $this : $classifier;
        $outsideClassifier = $direction === DirectionClassifier::NOP_OUTSIDE ? $this : $classifier;

        $xDiff = -$insideClassifier->getCenterPoint()->getX() - $outsideClassifier->getCenterPoint()->getX();
        $yDiff = $outsideClassifier->getCenterPoint()->getY() + $insideClassifier->getCenterPoint()->getY();
        $widthDiff = $insideClassifier->getWidth() - $outsideClassifier->getWidth();

        if (self::$estimator === null) {
            self::$estimator = PersistentModel::load(new Filesystem(__DIR__ . '/../../resources/Model/bigNopMatcher.model'));
        }

        return self::$estimator->proba(Unlabeled::quick([[$xDiff, $yDiff, $widthDiff]]))[0]['yes'] ?? 0;
    }

    /**
     * @param SmallWidthClassifier $classifier
     *
     * @throws SideClassifierException
     */
    public function compareSameSide(SideClassifierInterface $classifier): float
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $this->side->getClassifier(DirectionClassifier::class);
        $direction = $directionClassifier->getDirection();

        $insideClassifier = $direction === DirectionClassifier::NOP_INSIDE ? $this : $classifier;
        $outsideClassifier = $direction === DirectionClassifier::NOP_OUTSIDE ? $this : $classifier;

        $xDiff = $insideClassifier->getCenterPoint()->getX() - $outsideClassifier->getCenterPoint()->getX();
        $yDiff = $insideClassifier->getCenterPoint()->getY() - $insideClassifier->getCenterPoint()->getY();
        $widthDiff = $insideClassifier->getWidth() - $outsideClassifier->getWidth();

        return 1 - ((min(1, abs($xDiff) / 10) + min(1, abs($yDiff) / 10) + min(1, abs($widthDiff) / 10)) / 3);
    }

    public function getSmallestWidthIndex(): int
    {
        return $this->smallestWidthIndex;
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
            'width' => $this->width,
            'smallestWidthIndex' => $this->smallestWidthIndex,
            'centerPoint' => $this->centerPoint->jsonSerialize(),
        ];
    }
}
