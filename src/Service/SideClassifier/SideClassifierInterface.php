<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideClassifier;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use JsonSerializable;

interface SideClassifierInterface extends JsonSerializable
{
    /**
     * @throws SideClassifierException
     */
    public function __construct(Side $side);

    /**
     * Used to see, if this side can be attached to the side of another piece.
     *
     * @throws SideClassifierException
     *
     * @return float return value between 0 - 1 how same the sides are
     */
    public function compareOppositeSide(self $classifier): float;

    /**
     * Used to see, if this side is equal to another side (aka is it the same piece).
     *
     * @throws SideClassifierException
     *
     * @return float return value between 0 - 1 how same the sides are
     */
    public function compareSameSide(self $classifier): float;
}
