<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Rubix\ML\CrossValidation\Metrics\RSquared;
use Rubix\ML\CrossValidation\Metrics\SMAPE;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Cosine;
use Rubix\ML\Kernels\Distance\Diagonal;
use Rubix\ML\Kernels\Distance\SafeEuclidean;
use Rubix\ML\Kernels\SVM\RBF;
use Rubix\ML\Learner;
use Rubix\ML\NeuralNet\ActivationFunctions\ReLU;
use Rubix\ML\NeuralNet\CostFunctions\HuberLoss;
use Rubix\ML\NeuralNet\CostFunctions\LeastSquares;
use Rubix\ML\NeuralNet\Layers\Activation;
use Rubix\ML\NeuralNet\Layers\Dense;
use Rubix\ML\NeuralNet\Optimizers\Adam;
use Rubix\ML\NeuralNet\Optimizers\RMSProp;
use Rubix\ML\Regressors\Adaline;
use Rubix\ML\Regressors\DummyRegressor;
use Rubix\ML\Regressors\ExtraTreeRegressor;
use Rubix\ML\Regressors\GradientBoost;
use Rubix\ML\Regressors\KDNeighborsRegressor;
use Rubix\ML\Regressors\KNNRegressor;
use Rubix\ML\Regressors\MLPRegressor;
use Rubix\ML\Regressors\RadiusNeighborsRegressor;
use Rubix\ML\Regressors\RegressionTree;
use Rubix\ML\Regressors\Ridge;
use Rubix\ML\Regressors\SVR;
use Rubix\ML\Strategies\Constant;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:model:depth:create')]
class CreateDepthModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function getClassifierClassName(): string
    {
        return DepthClassifier::class;
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(30, 6, 1e-4, 20, null);
    }
}
