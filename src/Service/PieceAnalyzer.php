<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Context\BorderFinderContextInterface;
use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\SideClassifier\SideClassifierInterface;
use GdImage;

class PieceAnalyzer
{
    private PointService $pointService;

    private PathService $pathService;

    public function __construct(
        private BorderFinderInterface $borderFinder,
        private SideFinderInterface $sideFinder,
    ) {
        $this->pointService = new PointService();
        $this->pathService = new PathService();
    }

    /**
     * @throws BorderParsingException
     * @throws SideParsingException
     */
    public function getPieceFromImage(int $pieceIndex, GdImage $image, BorderFinderContextInterface $context): Piece
    {
        $borderPoints = $this->borderFinder->findPieceBorder($image, $context);

        /** @var DerivativePoint[] $borderPoints */
        $sides = $this->sideFinder->getSides($borderPoints);

        foreach ($sides as $side) {
            $points = $side->getPoints();
            $points = $this->pathService->softenPolyline($points, 10, 100);
            $points = array_slice($points, 5, 90);
            $points = $this->pathService->rotatePointsToCenter($points);

            $side->setPoints($points);

            $metadata = $this->createSideMetadata($side);

            /** @var class-string<SideClassifierInterface> $className */
            foreach (SideMatcherInterface::CLASSIFIER_CLASS_NAMES as $className) {
                try {
                    $side->addClassifier($className::fromMetadata($metadata));
                } catch (SideClassifierException) {
                }
            }
        }

        return new Piece($pieceIndex, $borderPoints, $sides, imagesx($image), imagesy($image));
    }

    private function createSideMetadata(Side $side): SideMetadata
    {
        $points = $side->getPoints();
        $pointsCount = count($points);

        $deepestIndex = 0;
        $depth = 0;
        foreach ($points as $index => $point) {
            if (abs($point->getY()) > abs($depth)) {
                $depth = $point->getY();
                $deepestIndex = $index;
            }
        }

        $yMultiplier = $depth < 0 ? -1 : 1;

        $pointWidths = [];
        $latestCompareIndex = $deepestIndex + 1;
        for ($i = $deepestIndex; $i >= 0; --$i) {
            for ($j = $latestCompareIndex; $j < $pointsCount; ++$j) {
                if ($points[$j]->getY() * $yMultiplier <= $points[$i]->getY() * $yMultiplier) {
                    if (abs($points[$j - 1]->getY() - $points[$i]->getY()) < abs($points[$j]->getY() - $points[$i]->getY())) {
                        --$j;
                    }

                    $pointWidths[$i] = $points[$j]->getX() - $points[$i]->getX();
                    $latestCompareIndex = $j;

                    continue 2;
                }
            }

            $pointWidths[$i] = $points[$j - 1]->getX() - $points[$i]->getX();
            $latestCompareIndex = $j - 1;
        }

        return new SideMetadata(
            $side,
            $this->pointService->getDistanceBetweenPoints($points[0], $points[count($points) - 1]),
            $depth,
            $deepestIndex,
            $pointWidths
        );
    }
}
