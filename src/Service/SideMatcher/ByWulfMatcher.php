<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;

class ByWulfMatcher implements SideMatcherInterface
{
    public function getMatchingProbability(Side $side1, Side $side2): float
    {
        /** @var DirectionClassifier $direction1 */
        $direction1 = $side1->getClassifier(DirectionClassifier::class);
        /** @var DirectionClassifier $direction2 */
        $direction2 = $side2->getClassifier(DirectionClassifier::class);

        if ($direction1->getDirection() === DirectionClassifier::NOP_STRAIGHT || $direction2->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return 0;
        }

        if ($direction1->getDirection() === $direction2->getDirection()) {
            return 0;
        }

        $classifiers = [
            BigWidthClassifier::class,
            SmallWidthClassifier::class,
        ];

        $sum = 0;
        foreach ($classifiers as $classifier) {
            try {
                $sum += $side1->getClassifier($classifier)->compareOppositeSide($side2->getClassifier($classifier));
            } catch (SideClassifierException) {
                // One of the classifiers didn't exist. So no matching possible.
                return 0;
            }
        }

        return $sum / count($classifiers);
    }
}
