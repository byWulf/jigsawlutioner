<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SideClassifierInterface;
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
        private array $points,
        private Point $startPoint,
        private Point $endPoint
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

    public function getStartPoint(): Point
    {
        return $this->startPoint;
    }

    public function getEndPoint(): Point
    {
        return $this->endPoint;
    }

    public function getDirection(): int
    {
        /** @var DirectionClassifier $directionClassifier */
        $directionClassifier = $this->getClassifier(DirectionClassifier::class);

        return $directionClassifier->getDirection();
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
            'startPoint' => $this->startPoint->jsonSerialize(),
            'endPoint' => $this->endPoint->jsonSerialize(),
        ];
    }
}
