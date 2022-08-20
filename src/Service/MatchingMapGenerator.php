<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Parallel\TaskExecutor;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\Task\MatchingMapGeneratorTask;

class MatchingMapGenerator
{
    private TaskExecutor $taskExecutor;

    public function __construct(
        private readonly SideMatcherInterface $sideMatcher,
        private readonly int $parallelTasks = 10,
    ) {
        $this->taskExecutor = new TaskExecutor();
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

        $tasks = [];
        foreach (array_chunk($pieces, 50) as $piecesBatch) {
            $tasks[] = new MatchingMapGeneratorTask($this->sideMatcher, $piecesBatch, $allSides);
        }

        /** @var array<int, float[][]> $results */
        $results = $this->taskExecutor->execute($tasks, $this->parallelTasks);

        return array_merge(...$results);
    }

    private function getKey(int $pieceNumber, int $sideIndex): string
    {
        return $pieceNumber . '_' . (($sideIndex + 4) % 4);
    }
}
