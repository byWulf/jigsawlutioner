<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\Service\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\Service\SideClassifier\SideClassifierInterface;
use Bywulf\Jigsawlutioner\Service\SideClassifier\SmallWidthClassifier;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use GdImage;

class PieceAnalyzer
{
    private PathService $pathService;

    /**
     * @var class-string[]
     */
    private array $classifierClassNames = [
        DirectionClassifier::class,
        BigWidthClassifier::class,
        SmallWidthClassifier::class,
    ];

    public function __construct(
        private BorderFinderInterface $borderFinder,
        private SideFinderInterface $sideFinder
    ) {
        $this->pathService = new PathService();
    }

    /**
     * @throws BorderParsingException
     * @throws SideParsingException
     */
    public function getPieceFromImage(GdImage $image): Piece
    {
        $borderPoints = $this->borderFinder->findPieceBorder($image);

        $sides = $this->sideFinder->getSides($borderPoints);

        foreach ($sides as $side) {
            $points = $side->getPoints();
            $points = $this->pathService->softenPolyline($points, 10, 100);
            $points = $this->pathService->rotatePointsToCenter($points);

            $side->setPoints($points);

            foreach ($this->classifierClassNames as $className) {
                try {
                    $classifier = new $className($side);
                    if (!$classifier instanceof SideClassifierInterface) {
                        throw new SideClassifierException('Wrong Classifier class given.');
                    }

                    $side->addClassifier($classifier);
                } catch (SideClassifierException) {
                }
            }
        }

        return new Piece($borderPoints, $sides);
    }
}
