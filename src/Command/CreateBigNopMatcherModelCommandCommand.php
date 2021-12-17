<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Learner;
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

        return [
            -$insideClassifier->getCenterPoint()->getX() - $outsideClassifier->getCenterPoint()->getX(),
            $outsideClassifier->getCenterPoint()->getY() + $insideClassifier->getCenterPoint()->getY(),
            $insideClassifier->getWidth() - $outsideClassifier->getWidth(),
        ];
    }

    protected function createLearner(): Learner
    {
        return new ClassificationTree(PHP_INT_MAX, 5);
    }
}
