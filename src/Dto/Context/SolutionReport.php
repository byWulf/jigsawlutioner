<?php

namespace Bywulf\Jigsawlutioner\Dto\Context;

use Bywulf\Jigsawlutioner\Dto\Solution;

class SolutionReport
{
    /**
     * @param array<string, array<string, float>>    $matchingMap
     */
    public function __construct(
        private readonly int $solutionStep,
        private readonly Solution $solution,
        private readonly array $matchingMap,
    )
    {
    }

    public function getSolutionStep(): int
    {
        return $this->solutionStep;
    }

    public function getSolution(): Solution
    {
        return $this->solution;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getMatchingMap(): array
    {
        return $this->matchingMap;
    }

}
