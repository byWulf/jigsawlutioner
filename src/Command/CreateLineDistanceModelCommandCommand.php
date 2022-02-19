<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\SideClassifier\LineDistanceClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:model:line-distance:create')]
class CreateLineDistanceModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function getClassifierClassName(): string
    {
        return LineDistanceClassifier::class;
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(50, 6, 1e-4, 20, null);
    }
}
