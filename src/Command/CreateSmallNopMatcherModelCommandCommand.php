<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Rubix\ML\Learner;
use Rubix\ML\Regressors\RegressionTree;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:model:small-nop:create')]
class CreateSmallNopMatcherModelCommandCommand extends AbstractModelCreatorCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->createModel($output, 'smallNopMatcher.model');

        return self::SUCCESS;
    }

    protected function getData(Side $insideSide, Side $outsideSide): array
    {
        /** @var SmallWidthClassifier $insideClassifier */
        $insideClassifier = $insideSide->getClassifier(SmallWidthClassifier::class);

        /** @var SmallWidthClassifier $outsideClassifier */
        $outsideClassifier = $outsideSide->getClassifier(SmallWidthClassifier::class);

        return $insideClassifier->getPredictionData($outsideClassifier);
    }

    protected function createLearner(): Learner
    {
        return new RegressionTree(20, 2, 1e-3, 10, null);
    }
}
