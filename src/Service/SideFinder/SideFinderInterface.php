<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideFinder;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideParsingException;

interface SideFinderInterface
{
    /**
     * @param Point[] $borderPoints
     *
     * @throws SideParsingException
     *
     * @return Side[]
     */
    public function getSides(array &$borderPoints): array;
}
