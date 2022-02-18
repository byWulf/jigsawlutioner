<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:model:small-nop:create')]
class CreateSmallNopModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function getClassifierClassName(): string
    {
        return SmallWidthClassifier::class;
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(30, 4, 1e-4, 20, null);
        return new RegressionTree(20, 2, 1e-3, 10, null);
    }
}
