<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
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
        return new RegressionTree(20, 2, 1e-3, 10, null);
    }
}
