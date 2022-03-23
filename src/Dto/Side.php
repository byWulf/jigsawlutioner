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
     * @var Point[]
     */
    private array $unrotatedPoints = [];

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
     * @template T of SideClassifierInterface
     *
     * @param class-string<T> $classifierClassName
     *
     * @throws SideClassifierException
     *
     * @return T
     */
    public function getClassifier(string $classifierClassName): object
    {
        /** @var T $classifier */
        $classifier = $this->classifiers[$classifierClassName] ?? throw new SideClassifierException('Classifier "' . $classifierClassName . '" not set.');

        return $classifier;
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

    /**
     * @return Point[]
     */
    public function getUnrotatedPoints(): array
    {
        return $this->unrotatedPoints;
    }

    /**
     * @param Point[] $unrotatedPoints
     */
    public function setUnrotatedPoints(array $unrotatedPoints): Side
    {
        $this->unrotatedPoints = $unrotatedPoints;

        return $this;
    }

    public function getDirection(): int
    {
        return $this->getClassifier(DirectionClassifier::class)->getDirection();
    }

    public function jsonSerialize(): array
    {
        return [
            'points' => array_map(
                static fn (Point $point): array => $point->jsonSerialize(),
                $this->points
            ),
            'classifiers' => array_map(
                static fn (SideClassifierInterface $classifier) => $classifier->jsonSerialize(),
                $this->classifiers
            ),
            'startPoint' => $this->startPoint->jsonSerialize(),
            'endPoint' => $this->endPoint->jsonSerialize(),
        ];
    }
}
