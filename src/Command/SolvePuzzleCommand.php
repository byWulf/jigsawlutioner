<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\PuzzleSolverInterface;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:puzzle:solve')]
class SolvePuzzleCommand extends Command
{
    use PieceLoaderTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $solver = new ByWulfSolver(
            new WeightedMatcher(),
            new ConsoleLogger($output)
        );
        $solution = $solver->findSolution($this->getPieces(false));

        $solution->outputSolution();

        return Command::SUCCESS;
    }
}
