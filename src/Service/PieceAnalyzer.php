<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Context\BorderFinderContextInterface;
use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\PieceAnalyzerException;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SideClassifierInterface;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
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
     * @throws PieceAnalyzerException
     */
    public function getPieceFromImage(int $pieceIndex, GdImage $image, BorderFinderContextInterface $context): Piece
    {
        $borderPoints = $this->borderFinder->findPieceBorder($image, $context);

        /** @var DerivativePoint[] $borderPoints */
        $sides = $this->sideFinder->getSides($borderPoints);

        if (count($sides) !== 4) {
            throw new PieceAnalyzerException('Could not find four sides of the piece.');
        }

        foreach ($sides as $side) {
            $points = $side->getPoints();
            $points = $this->pathService->softenPolyline($points, 10, 100);

            $movedPoints = [];
            foreach ($points as $index => $point) {
                if (isset($points[$index - 1], $points[$index + 1])) {
                    $rotation = $this->pointService->getRotation($points[$index - 1], $points[$index + 1]) + 90;

                    $point = $this->pointService->movePoint($point, $rotation, 2);
                }

                $movedPoints[] = $point;
            }

            $points = array_slice($movedPoints, 5, 90);
            $points = $this->pathService->softenPolyline($points, 0, 100);

            $side->setUnrotatedPoints($points);
            $side->setPoints($this->pathService->rotatePointsToCenter($points));

            $metadata = $this->createSideMetadata($side);

            /** @var class-string<SideClassifierInterface> $className */
            foreach (SideMatcherInterface::CLASSIFIER_CLASS_NAMES as $className) {
                try {
                    $side->addClassifier($className::fromMetadata($metadata));
                } catch (SideClassifierException) {
                    // If a classifier could not be created, don't add it to the side
                }
            }
        }

        $piece = new Piece($pieceIndex, $borderPoints, $sides, imagesx($image), imagesy($image));

        $this->drawCornersOnImage($piece, $image);
        $this->drawBorderPointsOnImage($piece, $image);
        $this->drawSidesOnImage($piece, $image);

        return $piece;
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

    /**
     * @throws PieceAnalyzerException
     */
    private function drawCornersOnImage(Piece $piece, GdImage $image): void
    {
        $cornerColor = $this->allocateColor($image, 255, 128, 0);
        foreach ($piece->getSides() as $side) {
            $point = $side->getStartPoint();
            for ($x = (int) $point->getX() - 10; $x < $point->getX() + 10; ++$x) {
                imagesetpixel($image, $x, (int) $point->getY(), $cornerColor);
            }
            for ($y = (int) $point->getY() - 10; $y < $point->getY() + 10; ++$y) {
                imagesetpixel($image, (int) $point->getX(), $y, $cornerColor);
            }
        }
    }

    /**
     * @throws PieceAnalyzerException
     */
    private function drawBorderPointsOnImage(Piece $piece, GdImage $image): void
    {
        foreach ($piece->getBorderPoints() as $point) {
            $color = $this->allocateColor($image, 255, 255, 255);
            if ($point instanceof DerivativePoint) {
                $diff = (int) min((abs($point->getDerivative()) / 90) * 255, 255);

                $color = $this->allocateColor(
                    $image,
                    255 - ($point->getDerivative() > 0 ? $diff : 0),
                    255 - ($point->getDerivative() < 0 ? $diff : 0),
                    255 - $diff
                );
                if ($point->isExtreme()) {
                    $color = $this->allocateColor($image, 255, 255, 0);
                }
            }
            imagesetpixel($image, (int) $point->getX(), (int) $point->getY(), $color);
        }
    }

    /**
     * @throws PieceAnalyzerException
     */
    private function drawSidesOnImage(Piece $piece, GdImage $image): void
    {
        $textColor = $this->allocateColor($image, 0, 0, 0);
        $lineColor = $this->allocateColor($image, 50, 80, 255);
        $highlightColor = $this->allocateColor($image, 0, 150, 150);

        $resizeFactor = 3;
        foreach ($piece->getSides() as $sideIndex => $side) {
            foreach ($side->getPoints() as $point) {
                imagesetpixel($image, (int) (($point->getX() / $resizeFactor) + 300 / $resizeFactor), (int) (($point->getY() / $resizeFactor) + 50 + $sideIndex * 250 / $resizeFactor), $lineColor);
            }

            foreach ([BigWidthClassifier::class, SmallWidthClassifier::class] as $classifier) {
                try {
                    $bigWidthClassifier = $side->getClassifier($classifier);
                    $centerPoint = $bigWidthClassifier->getCenterPoint();
                    imagesetpixel($image, (int) (($centerPoint->getX() / $resizeFactor) + 300 / $resizeFactor), (int) (($centerPoint->getY() / $resizeFactor) + 50 + $sideIndex * 250 / $resizeFactor), $highlightColor);
                } catch (SideClassifierException) {
                    // Don't draw in the case of nonexistent classifier
                }
            }

            $classifiers = $side->getClassifiers();
            ksort($classifiers);

            $yOffset = 0;
            foreach ($classifiers as $classifier) {
                imagestring($image, 1, (int) (600 / $resizeFactor), (int) ($yOffset + $sideIndex * 250 / $resizeFactor), (string) $classifier, $textColor);
                $yOffset += 10;
            }
        }
    }

    /**
     * @throws PieceAnalyzerException
     */
    private function allocateColor(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);

        if ($color === false) {
            throw new PieceAnalyzerException('Could not allocate color ' . $red . '/' . $green . '/' . $blue . '.');
        }

        return $color;
    }
}
