<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;

class PieceRecognizer
{
    /**
     * Returns a new piece cloned by the original piece with its sides rotated to match the sides of the existing piece.
     *
     * @param Piece[] $existingPieces
     */
    public function findExistingPiece(Piece $piece, array $existingPieces): ?Piece
    {
        $bestExistingPiece = null;
        $bestProbability = 0;
        $bestSideOffset = 0;

        foreach ($existingPieces as $existingPiece) {
            for ($sideOffset = 0; $sideOffset < 4; ++$sideOffset) {
                $probability = $this->getMatchingProbability($piece, $existingPiece, $sideOffset);

                if ($probability > $bestProbability) {
                    $bestProbability = $probability;
                    $bestExistingPiece = $existingPiece;
                    $bestSideOffset = $sideOffset;
                }
            }
        }

        if ($bestExistingPiece === null) {
            return null;
        }

        $sides = $piece->getSides();
        for ($i = 0; $i < $bestSideOffset; ++$i) {
            /** @var Side $side */
            $side = array_pop($sides);
            array_splice($sides, 0, 0, [$side]);
        }

        return new Piece(
            $bestExistingPiece->getIndex(),
            $piece->getBorderPoints(),
            $sides,
            $piece->getImageWidth(),
            $piece->getImageHeight(),
        );
    }

    private function getMatchingProbability(Piece $piece, Piece $comparePiece, int $sideOffset): float
    {
        $probabilitySum = 0;

        for ($sideIndex = 0; $sideIndex < 4; ++$sideIndex) {
            $side = $piece->getSide($sideIndex);
            $existingSide = $comparePiece->getSide($sideIndex + $sideOffset);

            if ($side->getDirection() !== $existingSide->getDirection()) {
                continue;
            }

            $probabilities = [];
            foreach ($side->getClassifiers() as $classifier) {
                try {
                    $existingClassifier = $existingSide->getClassifier($classifier::class);
                } catch (SideClassifierException) {
                    continue 2;
                }

                $probabilities[] = $classifier->compareSameSide($existingClassifier);
            }

            $probabilitySum += array_sum($probabilities) / count($probabilities);
        }

        return $probabilitySum;
    }
}
