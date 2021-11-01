<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Service\SideClassifier\SideClassifierInterface;
use JsonSerializable;

class Side implements JsonSerializable
{
    /**
     * @var SideClassifierInterface[]
     */
    private array $classifiers = [];

    /**
     * @param Point[] $points
     */
    public function __construct(
        private array $points
    ) {
    }

    /**
     * @return Point[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @param Point[] $points
     */
    public function setPoints(array $points): self
    {
        $this->points = $points;

        return $this;
    }

    /**
     * @return SideClassifierInterface[]
     */
    public function getClassifiers(): array
    {
        return $this->classifiers;
    }

    /**
     * @param class-string $classifierClassName
     *
     * @throws SideClassifierException
     */
    public function getClassifier(string $classifierClassName): SideClassifierInterface
    {
        return $this->classifiers[$classifierClassName] ?? throw new SideClassifierException('Classifier "' . $classifierClassName . '" not set.');
    }

    public function addClassifier(SideClassifierInterface $classifier): self
    {
        $this->classifiers[$classifier::class] = $classifier;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'points' => array_map(
                fn (Point $point): array => $point->jsonSerialize(),
                $this->points
            ),
            'classifiers' => array_map(
                fn (SideClassifierInterface $classifier) => $classifier->jsonSerialize(),
                $this->classifiers
            ),
        ];
    }
}
