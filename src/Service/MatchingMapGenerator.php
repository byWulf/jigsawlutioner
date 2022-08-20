<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Amp\Parallel\Worker\DefaultPool;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\Task\MatchingMapGeneratorTask;
use function Amp\call;
use function Amp\Promise\all;
use function Amp\Promise\wait;

class MatchingMapGenerator
{
    public function __construct(
        private readonly SideMatcherInterface $sideMatcher,
        private readonly int $parallelTasks = 10,
    ) {
    }

    /**
     * @param Piece[] $pieces
     *
     * @return float[][]
     */
    public function getMatchingMap(array $pieces): array
    {
        $piecesCount = count($pieces);
        if ($piecesCount === 0) {
            return [];
        }

        $allSides = [];
        foreach ($pieces as $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $allSides[$this->getKey($piece->getIndex(), $sideIndex)] = $side;
            }
        }

        $pool = new DefaultPool($this->parallelTasks);

        $coroutines = [];
        foreach (array_chunk($pieces, 50) as $piecesBatch) {
            $coroutines[] = call(fn() => yield $pool->enqueue(new MatchingMapGeneratorTask($this->sideMatcher, $piecesBatch, $allSides)));
        }

        /** @var array<int, float[][]> $results */
        $results = wait(all($coroutines));

        return array_merge(...$results);
    }

    private function getKey(int $pieceNumber, int $sideIndex): string
    {
        return $pieceNumber . '_' . (($sideIndex + 4) % 4);
    }
}
