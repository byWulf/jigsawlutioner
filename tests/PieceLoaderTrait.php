<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests;

use Bywulf\Jigsawlutioner\Dto\Piece;

trait PieceLoaderTrait
{
    /**
     * @return Piece[]
     */
    private function getPieces(): array
    {
        $pieces = [];
        for ($i = 2; $i <= 501; ++$i) {
            /** @var Piece $piece */
            $piece = unserialize(file_get_contents(__DIR__ . '/fixtures/pieces/piece' . $i . '_piece.ser'));

            if (count($piece->getSides()) !== 4) {
                continue;
            }

            // Reorder sides so the top side is the first side
            $sides = $piece->getSides();
            while (
                $sides[1]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY() ||
                $sides[2]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY() ||
                $sides[3]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY()
            ) {
                $side = array_splice($sides, 0, 1);
                $sides[] = $side[0];
                $sides = array_values($sides);
            }

            $piece->setSides(array_values($sides));

            $pieces[$i] = $piece;
        }

        return $pieces;
    }
}
