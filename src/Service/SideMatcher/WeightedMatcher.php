<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideMatcherException;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SideClassifierInterface;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;

class WeightedMatcher implements SideMatcherInterface
{
    private array $weights = [
        BigWidthClassifier::class => 0.960854029286,
        CornerDistanceClassifier::class => 0.84482933587476,
        DepthClassifier::class => 0.78565062388592,
        SmallWidthClassifier::class => 0.94630124777184,
    ];

    /**
     * @param Side[] $sides
     *
     * @throws SideMatcherException
     *
     * @return float[] Number between 0 and 1 for each given $sides
     */
    public function getMatchingProbabilities(Side $side, array $sides): array
    {
        $probabilities = [];
        foreach ($sides as $index => $otherSide) {
            $probabilities[$index] = $this->getMatchingProbability($side, $otherSide);
        }

        return $probabilities;
    }

    /**
     * @throws SideMatcherException
     *
     * @return float Number between 0 and 1
     */
    private function getMatchingProbability(Side $side1, Side $side2): float
    {
        $weightSum = 0;
        $sum = 0;
        /** @var class-string<SideClassifierInterface> $classifierClassName */
        foreach (SideMatcherInterface::CLASSIFIER_CLASS_NAMES as $classifierClassName) {
            try {
                $probability = $side1->getClassifier($classifierClassName)->compareOppositeSide($side2->getClassifier($classifierClassName));

                $probability = (float) min(1, max(0, $probability));

                $weight = $this->getWeightForClassifier($classifierClassName);
                if ($weight === null) {
                    if ($probability === 0.0) {
                        return 0;
                    }

                    if ($probability < 1.0) {
                        throw new SideMatcherException('"Take it or leave it" classifier ' . $classifierClassName . ' should only return 0 or 1, but returned ' . $probability . '.');
                    }

                    continue;
                }

                $sum += $probability * $weight;
                $weightSum += $weight;
            } catch (SideClassifierException) {
                // One of the classifiers didn't exist. So no matching possible.
                return 0;
            }
        }

        return $weightSum > 0 ? $sum / $weightSum : 0;
    }

    public function setWeights(array $weights): void
    {
        $this->weights = $weights;
    }

    private function getWeightForClassifier(string $classifierName): ?float
    {
        if ($classifierName === DirectionClassifier::class) {
            return null;
        }

        return $this->weights[$classifierName] ?? 0;
    }
}
