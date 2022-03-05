<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\ModelBasedClassifier;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Learner;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractModelCreatorCommand extends Command
{
    use PieceLoaderTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Loading nop information...');
        $nopInformation = $this->getNopInformation();

        $output->writeln('Creating datasets...');
        $datasets = [];
        $labels = [];
        for ($x = 0; $x < 25; ++$x) {
            for ($y = 0; $y < 20; ++$y) {
                $rightSide = $nopInformation[$y * 25 + $x + 2][3] ?? null;
                $rightOppositeSide = $nopInformation[$y * 25 + $x + 3][1] ?? null;

                $bottomSide = $nopInformation[$y * 25 + $x + 2][2] ?? null;
                $bottomOppositeSide = $nopInformation[($y + 1) * 25 + $x + 2][0] ?? null;

                $dataset = $this->getDataset($rightSide, $rightOppositeSide);
                if ($dataset !== null && $x < 24) {
                    $datasets[] = $dataset;
                    $labels[] = 1;
                }

                $dataset = $this->getDataset($bottomSide, $bottomOppositeSide);
                if ($dataset !== null && $y < 19) {
                    $datasets[] = $dataset;
                    $labels[] = 1;
                }

                $dataset = $this->findNonmatchingDataset($rightSide, $rightOppositeSide, $nopInformation[$y * 25 + $x + 4] ?? null);
                if ($dataset) {
                    $datasets[] = $dataset;
                    $labels[] = 0;
                }

                $dataset = $this->findNonmatchingDataset($bottomSide, $bottomOppositeSide, $nopInformation[$y * 25 + $x + 4] ?? null);
                if ($dataset) {
                    $datasets[] = $dataset;
                    $labels[] = 0;
                }
            }
        }

        $this->trainModel($output, $datasets, $labels);

        return self::SUCCESS;
    }

    private function findNonmatchingDataset(?Side $side1, ?Side $side2, ?array $piece): ?array
    {
        if ($side1 !== null && $side2 !== null && $piece !== null) {
            for ($i = 0; $i < 4; ++$i) {
                $otherSide = $piece[$i] ?? null;
                if (
                    $otherSide !== null &&
                    $otherSide->getClassifier(DirectionClassifier::class)->getDirection() === $side2->getClassifier(DirectionClassifier::class)->getDirection()
                ) {
                    return $this->getDataset($side1, $otherSide);
                }
            }
        }

        return null;
    }

    private function getDataset(?Side $side1, ?Side $side2): ?array
    {
        if ($side1 === null || $side1->getClassifier(DirectionClassifier::class) === DirectionClassifier::NOP_STRAIGHT) {
            return null;
        }
        if ($side2 === null || $side2->getClassifier(DirectionClassifier::class) === DirectionClassifier::NOP_STRAIGHT) {
            return null;
        }
        if ($side1->getClassifier(DirectionClassifier::class) === $side2->getClassifier(DirectionClassifier::class)) {
            return null;
        }

        // Make the inside-side the first side
        if ($side1->getClassifier(DirectionClassifier::class) !== DirectionClassifier::NOP_INSIDE) {
            $sideTmp = $side1;
            $side1 = $side2;
            $side2 = $sideTmp;
        }

        try {
            return $this->getData($side1, $side2);
        } catch (SideClassifierException) {
            return null;
        }
    }

    /**
     * @throws SideClassifierException
     */
    protected function getData(Side $side1, Side $side2): array
    {
        /** @var ModelBasedClassifier $insideClassifier */
        $insideClassifier = $side1->getClassifier($this->getClassifierClassName());
        $outsideClassifier = $side2->getClassifier($this->getClassifierClassName());
        return $insideClassifier->getPredictionData($outsideClassifier);
    }

    /**
     * @return class-string<ModelBasedClassifier>|null
     */
    abstract protected function getClassifierClassName(): ?string;

    protected function getModelPath(): string
    {
        return $this->getClassifierClassName()::getModelPath();
    }

    /**
     * @return Side[][]
     */
    private function getNopInformation(): array
    {
        $nopInformation = [];

        foreach ($this->getPieces('test_ordered', true) as $pieceIndex => $piece) {
            foreach ($piece->getSides() as $sideIndex => $side) {
                $nopInformation[$pieceIndex][$sideIndex] = $side;
            }
        }

        return $nopInformation;
    }

    /**
     * @param array $datasets
     * @param array $labels
     *
     * @return void
     */
    private function trainModel(OutputInterface $output, array $datasets, array $labels): void
    {
        $labeledDataset = new Labeled($datasets, $labels);
        list($training, $testing) = $labeledDataset->stratifiedSplit(0.8);

        $estimator = new PersistentModel(
            $this->createLearner(),
            new Filesystem($this->getModelPath())
        );

        $output->writeln('Training...');
        $estimator->train($training);

        $output->writeln('Predicting...');
        $predictions = $estimator->predict($testing);

        $difference = 0;
        foreach ($predictions as $index => $prediction) {
            $difference += abs($testing->label($index) - $prediction);
        }

        $score = 1 - ($difference / count($predictions));
        $output->writeln('Score is ' . $score);

        $estimator->save();
        $output->writeln('Model saved.');
    }

    abstract protected function createLearner(): Learner;
}
