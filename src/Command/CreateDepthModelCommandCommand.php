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


//        Adaline	Low	●	●	●	●	Continuous
        //return new Adaline(256, new Adam(0.01), 1e-2, 1000, 1e-9, 10, new HuberLoss(1)); // 0.5
//        Extra Tree Regressor	Medium		●		●	Categorical, Continuous
        //return new ExtraTreeRegressor(20, 1, 0.05, null);
//        Gradient Boost	High		●	●	●	Categorical, Continuous
        //return new GradientBoost(new RegressionTree(20, 1, 1e-3, 10, null), 1, 0.1, 10000, 1e-3, 5, 0.1, new SMAPE(), new DummyRegressor(new Constant(0.0)));
//        K-d Neighbors Regressor	Medium				●	Depends on distance kernel
        //return new KDNeighborsRegressor(1, true, new BallTree(10)); // 0.81818181818182 ################
//        KNN Regressor	Medium	●			●	Depends on distance kernel
        //return new KNNRegressor(2, true, new Cosine()); // 0.81818181811235
//        MLP Regressor	High	●		●	●	Continuous
//        Radius Neighbors Regressor	Medium				●	Depends on distance kernerl
//        Regression Tree	Medium		●		●	Categorical, Continuous
        return new RegressionTree(30, 4, 1e-4, 20, null);
        //return new RegressionTree(20, 2, 1e-3, 10, null); // 0.81363
//        Ridge	Low		●		●	Continuous
        //return new Ridge(10);
//        SVR	High					Continuous
    }
}
