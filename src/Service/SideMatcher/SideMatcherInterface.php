<?php

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;

interface SideMatcherInterface
{
    public function getMatchingProbability(Side $side1, Side $side2): float;
}
