<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;
use Bywulf\Jigsawlutioner\Service\BorderFinder\BorderFinderInterface;
use Bywulf\Jigsawlutioner\Service\SideFinder\SideFinderInterface;
use GdImage;

class PieceAnalyzer
{
    private PathService $pathService;

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

        $softenedSides = array_map(
            fn (Side $side): Side => new Side($this->pathService->softenPolyline($side->getPoints(), 10, 100)),
            $sides
        );

        $rotatedSides = array_map(
            fn (Side $side): Side => new Side($this->pathService->rotatePointsToCenter($side->getPoints())),
            $softenedSides
        );

        return new Piece($borderPoints, $rotatedSides);
    }
}
