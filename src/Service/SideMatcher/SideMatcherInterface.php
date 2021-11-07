<?php

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;

interface SideMatcherInterface
{
    public const CLASSIFIER_CLASS_NAMES = [
        DirectionClassifier::class,
        BigWidthClassifier::class,
        SmallWidthClassifier::class,
        CornerDistanceClassifier::class,
        DepthClassifier::class,
    ];

    /**
     * @param Side[] $sides
     *
     * @return float[]
     */
    public function getMatchingProbabilities(Side $side, array $sides): array;
}
