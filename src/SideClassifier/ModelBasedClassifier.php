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

    abstract public function getPredictionData(SideClassifierInterface $comparisonClassifier): array;

    public function compareOppositeSide(SideClassifierInterface $classifier): float
    {
        if (!isset(self::$estimators[static::class])) {
            self::$estimators[static::class] = PersistentModel::load(new Filesystem(static::getModelPath()));
        }

        $data = $this->getPredictionData($classifier);

        return self::$estimators[static::class]->predict(Unlabeled::quick([$data]))[0];
    }
}
