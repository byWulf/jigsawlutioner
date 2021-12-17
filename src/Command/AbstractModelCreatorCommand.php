<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\DerivativePoint;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\SideMetadata;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\SideClassifier\BigWidthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\CornerDistanceClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DepthClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\SideClassifier\SmallWidthClassifier;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Learner;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractModelCreatorCommand extends Command
{
    public function createModel(OutputInterface $output, string $modelFilename): void
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
                    $labels[] = 'yes';
                }

                $dataset = $this->getDataset($bottomSide, $bottomOppositeSide);
                if ($dataset !== null && $y < 19) {
                    $datasets[] = $dataset;
                    $labels[] = 'yes';
                }

                $dataset = $this->findNonmatchingDataset($rightSide, $rightOppositeSide, $nopInformation[$y * 25 + $x + 4] ?? null);
                if ($dataset) {
                    $datasets[] = $dataset;
                    $labels[] = 'no';
                }

                $dataset = $this->findNonmatchingDataset($bottomSide, $bottomOppositeSide, $nopInformation[$y * 25 + $x + 4] ?? null);
                if ($dataset) {
                    $datasets[] = $dataset;
                    $labels[] = 'no';
                }
            }
        }

        $this->trainModel($output, $datasets, $labels, $modelFilename);
    }

    /**
     * @param Piece $piece
     * @return Side[]
     */
    public function getReorderedSides(Piece $piece): array
    {
        $sides = $piece->getSides();

        while (
            $sides[1]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY() ||
            $sides[2]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY() ||
            $sides[3]->getStartPoint()->getY() < $sides[0]->getStartPoint()->getY()
        ) {
            $side = array_splice($sides, 0, 1);
            $sides[] = $side[0];
            $sides = array_values($sides);
        }

        return $sides;
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

    abstract protected function getData(Side $insideSide, Side $outsideSide): array;

    /**
     * @return Side[][]
     */
    private function getNopInformation(): array
    {
        $nopInformation = [];

        for ($i = 2; $i <= 501; ++$i) {
            $piece = Piece::fromSerialized(file_get_contents(__DIR__ . '/../../resources/Fixtures/Piece/piece' . $i . '_piece.ser'));

            if (count($piece->getSides()) !== 4) {
                continue;
            }

            // Reorder sides so the top side is the first side
            $sides = $this->getReorderedSides($piece);

            foreach (array_values($sides) as $index => $side) {
                $nopInformation[$i][$index] = $side;
            }
        }

        return $nopInformation;
    }

    /**
     * @param array $datasets
     * @param array $labels
     * @return void
     */
    private function trainModel(OutputInterface $output, array $datasets, array $labels, string $modelFilename): void
    {
        $labeledDataset = new Labeled($datasets, $labels);
        list($training, $testing) = $labeledDataset->stratifiedSplit(0.8);

        $estimator = new PersistentModel(
            $this->createLearner(),
            new Filesystem(__DIR__ . '/../../resources/Model/' . $modelFilename)
        );

        $output->writeln('Training...');
        $estimator->train($training);

        $output->writeln('Predicting...');
        $predictions = $estimator->proba($testing);

        $difference = 0;
        foreach ($predictions as $index => $prediction) {
            $difference += 1 - $prediction[$testing->label($index)];
        }

        $score = 1 - ($difference / count($predictions));
        $output->writeln('Score is ' . $score);

        $estimator->save();
        $output->writeln('Model saved.');
    }

    abstract protected function createLearner(): Learner;
}
