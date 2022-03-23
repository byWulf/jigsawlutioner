<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\SideClassifier;

use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

abstract class ModelBasedClassifier implements SideClassifierInterface
{
    /**
     * @var PersistentModel[]
     */
    private static array $estimators = [];

    abstract public static function getModelPath(): string;

    /**
     * @return array<mixed>
     */
    abstract public function getPredictionData(SideClassifierInterface $comparisonClassifier): array;

    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        $data = $this->getPredictionData($classifier);

        /** @var float $result */
        $result = $this->getEstimator()->predict(Unlabeled::quick([$data]))[0];

        return $result;
    }

    private function getEstimator(): PersistentModel
    {
        if (!isset(self::$estimators[static::class])) {
            self::$estimators[static::class] = PersistentModel::load(new Filesystem(static::getModelPath()));
        }

        return self::$estimators[static::class];
    }
}
