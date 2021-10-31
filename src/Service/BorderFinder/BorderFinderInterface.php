<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\BorderFinder;

use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Exception\BorderParsingException;
use GdImage;

interface BorderFinderInterface
{
    /**
     * @throws BorderParsingException
     *
     * @return Point[]
     */
    public function findPieceBorder(GdImage $image): array;
}
