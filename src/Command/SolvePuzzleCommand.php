<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\TryhardSolver;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\Service\SolutionOutputter;
use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:puzzle:solve')]
class SolvePuzzleCommand extends Command
{
    use PieceLoaderTrait;

    private string $currentMessage = '';
    private string $messages = '';
    private ConsoleSectionOutput $messageSection;

    protected function configure()
    {
        $this->addArgument('set', InputArgument::REQUIRED, 'Name of set folder');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force rebuilding the matchingMap and not loading it from cache.');
        $this->addArgument('pieces', InputArgument::OPTIONAL, 'Comma separated list of piece ids that should be processed from the given set.');
    }

    /**
     * @throws PuzzleSolverException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new InvalidArgumentException('Need a ConsoleOutput');
        }

        $solver = new ByWulfSolver(
            new WeightedMatcher(),
            new ConsoleLogger($output)
        );

        $setName = $input->getArgument('set');
        $pieces = $this->getPieces($setName, false);

        $pieceIdsText = $input->getArgument('pieces');
        if ($pieceIdsText !== null) {
            $pieceIds = array_map('intval', explode(',', $pieceIdsText));
            $pieces = array_filter($pieces, fn (Piece $piece): bool => in_array($piece->getIndex(), $pieceIds, true));
        }

        ProgressBar::setFormatDefinition('custom', '%message:14s%: %current%/%max% [%bar%]');

        $this->messageSection = $output->section();

        $groupsProgressBar = new ProgressBar($output->section(), count($pieces));
        $groupsProgressBar->setFormat('custom');
        $groupsProgressBar->setMessage('Groups');
        $groupsProgressBar->start();
        $groupsProgressBar->setProgress(count($pieces));
        $groupsProgressBar->display();

        $biggestGroupProgressBar = new ProgressBar($output->section(), count($pieces));
        $biggestGroupProgressBar->setFormat('custom');
        $biggestGroupProgressBar->setMessage('Biggest group');
        $biggestGroupProgressBar->start();

        $this->addMessage('Starting...');

        $solution = $solver->findSolution(
            $pieces,
            $setName,
            !$input->getOption('force'),
            function (string $message, int $groups, int $biggestGroup) use ($groupsProgressBar, $biggestGroupProgressBar): void {
                $this->addMessage($message);
                $groupsProgressBar->setProgress($groups);
                $biggestGroupProgressBar->setProgress($biggestGroup);
            }
        );

        $this->addMessage('');

        $groupsProgressBar->display();
        $biggestGroupProgressBar->display();

        $htmlFile = __DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/solution.html';
        $solutionOutputter = new SolutionOutputter();
        $solutionOutputter->outputAsHtml(
            $solution,
            $htmlFile,
            __DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/piece%s_transparent_small.png'
        );

        $output->writeln('Solution can be viewed at <href=file://' . $htmlFile . '>' . $setName . '/solution.html</>');

        return Command::SUCCESS;
    }

    private function addMessage(string $message): void
    {
        if ($this->currentMessage === $message) {
            return;
        }

        if ($this->currentMessage !== '') {
            $this->messages .= PHP_EOL . '<fg=#00ff00>✔ ' . $this->currentMessage . '</>';
        }
        $this->currentMessage = $message;

        $this->messageSection->overwrite($this->messages . ($message !== '' ? PHP_EOL . '<fg=#ff8800>⌛ ' . $message . '</>' : ''));
    }
}
