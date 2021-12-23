<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:model:big-nop:create')]
class CreateBigNopMatcherModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->createModel($output, 'bigNopMatcher.model');

        return self::SUCCESS;
    }

    protected function getData(Side $insideSide, Side $outsideSide): array
    {
        /** @var BigWidthClassifier $insideClassifier */
        $insideClassifier = $insideSide->getClassifier(BigWidthClassifier::class);

        /** @var BigWidthClassifier $outsideClassifier */
        $outsideClassifier = $outsideSide->getClassifier(BigWidthClassifier::class);

        return $insideClassifier->getPredictionData($outsideClassifier);
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(20, 2, 1e-3, 10, null);
    }
}
