<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\Service\SolutionOutputter;
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
        $solutionOutputter = new SolutionOutputter();

        $pieces = $this->getPieces(false);
        //$pieces = array_filter($pieces, fn (Piece $piece): bool => in_array($piece->getIndex(), [305, 280, 255, 304, 279, 254, 302, 303, 278, 327, 12]));

        $solution = $solver->findSolution($pieces);

        $solutionOutputter->outputAsText($solution);

        (new SolutionOutputter())->outputAsHtml(
            $solution,
            __DIR__ . '/../../resources/Fixtures/Piece/solution.html',
            __DIR__ . '/../../resources/Fixtures/Piece/piece%s_transparent.png'
        );

        return Command::SUCCESS;
    }
}
