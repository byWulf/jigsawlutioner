<?php

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;

interface SideMatcherInterface
{
    public function getMatchingProbability(Side $side1, Side $side2): float;

    /**
     * @param Side[] $sides
     * @return float[]
     */
    public function getMatchingProbabilities(Side $side, array $sides): array;
}
