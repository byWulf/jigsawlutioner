<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\SideMatcher;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Exception\SideMatcherException;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SideClassifierInterface;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;

class ByWulfMatcher implements SideMatcherInterface
{
    public function getMatchingProbability(Side $side1, Side $side2): float
    {
        $sum = 0;
        /** @var class-string<SideClassifierInterface> $classifierClassName */
        foreach (SideMatcherInterface::CLASSIFIER_CLASS_NAMES as $classifierClassName) {
            try {
                $probability = $side1->getClassifier($classifierClassName)->compareOppositeSide($side2->getClassifier($classifierClassName));

                if ($probability < 0.0 || $probability > 1.0) {
                    throw new SideMatcherException('Probability must be between 0 and 1, got ' . $probability . ' from ' . $classifierClassName . '.');
                }

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
            } catch (SideClassifierException) {
                // One of the classifiers didn't exist. So no matching possible.
                return 0;
            }
        }

        return $sum;
    }

    public function getMatchingProbabilities(Side $side, array $sides): array
    {
        $probabilities = [];
        foreach ($sides as $index => $otherSide) {
            $probabilities[$index] = $this->getMatchingProbability($side, $otherSide);
        }

        return $probabilities;
    }

    private function getWeightForClassifier(string $classifierName): ?float
    {
        return match ($classifierName) {
            DirectionClassifier::class => null,
            SmallWidthClassifier::class => 1.0,
            BigWidthClassifier::class => 1.0,
            default => 1.0
        };
    }
}
