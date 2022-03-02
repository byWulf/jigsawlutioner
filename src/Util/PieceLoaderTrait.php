<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Util;

use Bywulf\Jigsawlutioner\Dto\Piece;
use InvalidArgumentException;

trait PieceLoaderTrait
{
    /**
     * @return Piece[]
     */
    private function getPieces(string $setName, bool $reorderSides = true): array
    {
        $meta = json_decode(file_get_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/meta.json'), true);

        $pieces = [];
        foreach ($this->getPieceNumbers($meta) as $i) {
            if (!is_file(__DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/piece' . $i . '_piece.ser')) {
                continue;
            }

            /** @var Piece $piece */
            $piece = Piece::fromSerialized(file_get_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/piece' . $i . '_piece.ser'));

            if (count($piece->getSides()) !== 4) {
                continue;
            }

            // Reorder sides so the top side is the first side
            if ($reorderSides) {
                $targetTopSide = match ($meta['topLeftCorner']) {
                    'top' => 1,
                    'left' => 2,
                    'bottom' => 3,
                    'right' => 0,
                    default => throw new InvalidArgumentException('topLeftCorner from meta.json invalid'),
                };

                $sides = $piece->getSides();

                while (
                    $sides[($targetTopSide + 1) % 4]->getStartPoint()->getY() < $sides[$targetTopSide]->getStartPoint()->getY() ||
                    $sides[($targetTopSide + 2) % 4]->getStartPoint()->getY() < $sides[$targetTopSide]->getStartPoint()->getY() ||
                    $sides[($targetTopSide + 3) % 4]->getStartPoint()->getY() < $sides[$targetTopSide]->getStartPoint()->getY()
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

    private function getPieceNumbers(array $meta): array
    {
        if (!isset($meta['numbers']) && !isset($meta['min']) && !isset($meta['max'])) {
            throw new InvalidArgumentException('"numbers" or "min"+"max" have to be set in the meta.json.');
        }

        if (isset($meta['numbers']) && (isset($meta['min']) || isset($meta['max']))) {
            throw new InvalidArgumentException('Either "numbers" or "min"+"max" have to be set in the meta.json, but not both at the same time.');
        }

        if (isset($meta['numbers'])) {
            $numbers = $meta['numbers'];
        } else {
            if (!isset($meta['min']) || !isset($meta['max'])) {
                throw new InvalidArgumentException('When using number range, "min" and "max" have to be set at the same time.');
            }

            $numbers = [];
            for ($i = $meta['min']; $i <= $meta['max']; ++$i) {
                $numbers[] = $i;
            }
        }

        return $numbers;
    }
}
