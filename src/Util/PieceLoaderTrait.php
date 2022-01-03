<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Util;

use Bywulf\Jigsawlutioner\Dto\Piece;

trait PieceLoaderTrait
{
    /**
     * @return Piece[]
     */
    private function getPieces(string $setName, bool $reorderSides = true): array
    {
        $pieces = [];
        for ($i = 2; $i <= 501; ++$i) {
            /** @var Piece $piece */
            $piece = Piece::fromSerialized(file_get_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/piece' . $i . '_piece.ser'));

            if (count($piece->getSides()) !== 4) {
                continue;
            }

            // Reorder sides so the top side is the first side
            if ($reorderSides) {
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
            }

            $pieces[$i] = $piece;
        }

        return $pieces;
    }
}
