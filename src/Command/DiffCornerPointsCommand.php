<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\TryhardSolver;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\Service\SolutionOutputter;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:piece:corners:diff')]
class DiffCornerPointsCommand extends Command
{
    use PieceLoaderTrait;

    protected function configure(): void
    {
        $this->addArgument('set', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Name of set folder');
    }

    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $table = $io->createTable();
        $table->setHeaders(['Set', 'Piece', 'Old points', 'New points']);

        foreach ($input->getArgument('set') as $setName) {
            $pieces = $this->getPieces($setName, false);
            $oldPoints = json_decode(file_get_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/corner_points.json'), true, 512, JSON_THROW_ON_ERROR);

            foreach ($pieces as $piece) {
                $newPoints = json_encode([
                    ['x' => $piece->getSide(0)->getStartPoint()->getX(), 'y' => $piece->getSide(0)->getStartPoint()->getY()],
                    ['x' => $piece->getSide(1)->getStartPoint()->getX(), 'y' => $piece->getSide(1)->getStartPoint()->getY()],
                    ['x' => $piece->getSide(2)->getStartPoint()->getX(), 'y' => $piece->getSide(2)->getStartPoint()->getY()],
                    ['x' => $piece->getSide(3)->getStartPoint()->getX(), 'y' => $piece->getSide(3)->getStartPoint()->getY()],
                ], JSON_THROW_ON_ERROR);

                $comparePoints = json_encode($oldPoints[$piece->getIndex()] ?? [], JSON_THROW_ON_ERROR);

                if ($newPoints !== $comparePoints) {
                    $table->addRow([$setName, $piece->getIndex(), $comparePoints, $newPoints]);
                }
            }
        }

        $table->render();

        return Command::SUCCESS;
    }
}
