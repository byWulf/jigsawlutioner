<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto\Context;

use Bywulf\Jigsawlutioner\Dto\Solution;

class SolutionReport
{
    /**
     * @param array<int, string> $removedMatchingKeys
     */
    public function __construct(
        private readonly int $solutionStep,
        private readonly Solution $solution,
        private readonly array $removedMatchingKeys,
    ) {
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
     * @return array<int, string>
     */
    public function getRemovedMatchingKeys(): array
    {
        return $this->removedMatchingKeys;
    }
}
