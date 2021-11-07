<?php

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;

interface SideMatcherInterface
{
    public const CLASSIFIER_CLASS_NAMES = [
        DirectionClassifier::class,
        BigWidthClassifier::class,
        SmallWidthClassifier::class,
    ];

    public function getMatchingProbability(Side $side1, Side $side2): float;

    /**
     * @param Side[] $sides
     *
     * @return float[]
     */
    public function getMatchingProbabilities(Side $side, array $sides): array;
}
