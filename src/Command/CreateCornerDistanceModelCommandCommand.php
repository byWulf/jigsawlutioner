<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:model:corner-distance:create')]
class CreateCornerDistanceModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function getClassifierClassName(): string
    {
        return CornerDistanceClassifier::class;
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(20, 2, 1e-3, 10, null);
    }
}
