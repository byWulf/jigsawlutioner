<?php

namespace Bywulf\Jigsawlutioner\Dto\Context;

use Bywulf\Jigsawlutioner\Dto\Solution;

class SolutionReport
{
    /**
     * @param Solution $solution
     * @param array<string, array<string, float>>    $matchingMap
     */
    public function __construct(
        private readonly Solution $solution,
        private readonly array $matchingMap,
    )
    {
    }

    public function getSolution(): Solution
    {
        return $this->solution;
    }

    public function getMatchingMap(): array
    {
        return $this->matchingMap;
    }

}
