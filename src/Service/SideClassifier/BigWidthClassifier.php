<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;

class BigWidthClassifier implements SideClassifierInterface
{
    /**
     * @var array<int, float>
     */
    private array $pointWidths = [];

    private int $biggestWidthIndex = 0;

    private Point $centerPoint;

    public function __construct(private Side $side)
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $side->getClassifier(DirectionClassifier::class);
        if ($directionClassifier->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            throw new SideClassifierException('Not available on straight sides.');
        }

        $points = $side->getPoints();
        $pointsCount = count($points);

        $yMultiplier = $directionClassifier->getDirection() === DirectionClassifier::NOP_INSIDE ? -1 : 1;

        $gettingBigger = true;
        $latestCompareIndex = $directionClassifier->getDeepestIndex() + 1;
        for ($i = $directionClassifier->getDeepestIndex() - 5; $i >= 0; --$i) {
            for ($j = $latestCompareIndex; $j < $pointsCount; ++$j) {
                if ($points[$j]->getY() * $yMultiplier <= $points[$i]->getY() * $yMultiplier) {
                    if (abs($points[$j - 1]->getY() - $points[$i]->getY()) < abs($points[$j]->getY() - $points[$i]->getY())) {
                        --$j;
                    }

                    $this->pointWidths[$i] = $points[$j]->getX() - $points[$i]->getX();
                    $latestCompareIndex = $j;

                    if (isset($this->pointWidths[$i + 1]) && $this->pointWidths[$i] < $this->pointWidths[$i + 1] && $gettingBigger) {
                        $gettingBigger = false;
                        $this->biggestWidthIndex = $i + 1;
                    }

                    continue 2;
                }
            }
            $this->pointWidths[$i] = $points[$j - 1]->getX() - $points[$i]->getX();
            $latestCompareIndex = $j - 1;
        }

        if ($this->biggestWidthIndex === 0) {
            throw new SideClassifierException('Couldn\'t determine biggest width of nop.');
        }

        $this->centerPoint = new Point(
            $points[$this->biggestWidthIndex]->getX() + $this->pointWidths[$this->biggestWidthIndex] / 2,
            $points[$this->biggestWidthIndex]->getY()
        );
    }

    /**
     * @param BigWidthClassifier $classifier
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
        $yDiff = $outsideClassifier->getCenterPoint()->getY() + $insideClassifier->getCenterPoint()->getY() - 5;
        $widthDiff = $insideClassifier->getWidth() - $outsideClassifier->getWidth() - 6;

        return 1 - ((min(1, abs($xDiff) / 10) + min(1, abs($yDiff) / 10) + min(1, abs($widthDiff) / 10)) / 3);
    }

    /**
     * @param BigWidthClassifier $classifier
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

    /**
     * @return array<int, float>
     */
    public function getPointWidths(): array
    {
        return $this->pointWidths;
    }

    public function getBiggestWidthIndex(): int
    {
        return $this->biggestWidthIndex;
    }

    public function getWidth(): float
    {
        return $this->pointWidths[$this->biggestWidthIndex];
    }

    public function getCenterPoint(): Point
    {
        return $this->centerPoint;
    }

    public function jsonSerialize(): array
    {
        return [
            'pointWidths' => $this->pointWidths,
            'biggestWidthIndex' => $this->biggestWidthIndex,
            'centerPoint' => $this->centerPoint->jsonSerialize(),
        ];
    }
}
