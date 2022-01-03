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
        //$pieces = array_filter($pieces, fn (Piece $piece): bool => in_array($piece->getIndex(), [149,124,382,438,463,464,489,487,486,488]));

        $solution = $solver->findSolution($pieces, !$input->getOption('force'));

        //$solutionOutputter->outputAsText($solution);

        $solutionOutputter->outputAsHtml(
            $solution,
            __DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/solution.html',
            __DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/piece%s_transparent.png'
        );

        return Command::SUCCESS;
    }
}
