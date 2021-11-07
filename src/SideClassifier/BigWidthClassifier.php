<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use JsonSerializable;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

class BigWidthClassifier implements SideClassifierInterface
{
    private static ?PersistentModel $estimator = null;

    public function __construct(
        private string $direction,
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
        for ($i = $metadata->getDeepestIndex(); $i >= 0; $i--) {
            // Search for the first width, that gets smaller than the width before
            if (isset($pointWidths[$i + 1]) && $pointWidths[$i] < $pointWidths[$i + 1]) {
                return new BigWidthClassifier(
                    $directionClassifier->getDirection(),
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

    /**
     * @param BigWidthClassifier $classifier
     */
    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        $insideClassifier = $this->direction === DirectionClassifier::NOP_INSIDE ? $this : $classifier;
        $outsideClassifier = $this->direction === DirectionClassifier::NOP_OUTSIDE ? $this : $classifier;

        $xDiff = -$insideClassifier->getCenterPoint()->getX() - $outsideClassifier->getCenterPoint()->getX();
        $yDiff = $outsideClassifier->getCenterPoint()->getY() + $insideClassifier->getCenterPoint()->getY();
        $widthDiff = $insideClassifier->getWidth() - $outsideClassifier->getWidth();

        if (self::$estimator === null) {
            self::$estimator = PersistentModel::load(new Filesystem(__DIR__ . '/../../resources/Model/bigNopMatcher.model'));
        }

        return self::$estimator->proba(Unlabeled::quick([[$xDiff, $yDiff, $widthDiff]]))[0]['yes'] ?? 0;
    }

    /**
     * @param BigWidthClassifier $classifier
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
