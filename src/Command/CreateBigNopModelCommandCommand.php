<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:model:big-nop:create')]
class CreateBigNopModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function getClassifierClassName(): string
    {
        return BigWidthClassifier::class;
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(20, 2, 1e-3, 10, null);
    }
}
