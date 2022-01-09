<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\Service\SolutionOutputter;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:puzzle:solve')]
class SolvePuzzleCommand extends Command
{
    use PieceLoaderTrait;

    protected function configure()
    {
        $this->addArgument('set', InputArgument::REQUIRED, 'Name of set folder');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force rebuilding the matchingMap and not loading it from cache.');
        $this->addArgument('pieces', InputArgument::OPTIONAL, 'Comma separated list of piece ids that should be processed from the given set.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $solver = new ByWulfSolver(
            new WeightedMatcher(),
            new ConsoleLogger($output)
        );
        $solutionOutputter = new SolutionOutputter();

        $setName = $input->getArgument('set');

        $pieces = $this->getPieces($setName, false);

        $pieceIdsText = $input->getArgument('pieces');
        if ($pieceIdsText !== null) {
            $pieceIds = array_map('intval', explode(',', $pieceIdsText));
            $pieces = array_filter($pieces, fn (Piece $piece): bool => in_array($piece->getIndex(), $pieceIds));
        }

        $solution = $solver->findSolution($pieces, $setName, !$input->getOption('force'));

        //$solutionOutputter->outputAsText($solution);

        $solutionOutputter->outputAsHtml(
            $solution,
            __DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/solution.html',
            __DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/piece%s_transparent.png'
        );

        return Command::SUCCESS;
    }
}
