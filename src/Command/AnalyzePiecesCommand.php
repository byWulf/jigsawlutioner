<?php

namespace Bywulf\Jigsawlutioner\Command;

use Bywulf\Jigsawlutioner\Util\PieceLoaderTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:pieces:analyze')]
class AnalyzePiecesCommand extends Command
{
    use PieceLoaderTrait;

    protected function configure()
    {
        $this->addArgument('set', InputArgument::REQUIRED, 'Name of set folder');
        $this->addArgument('pieceNumber', InputArgument::IS_ARRAY, 'Piece number if you only want to analyze one piece. If omitted all pieces will be analyzed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('analyze', false, false, false, false);
        $channel->exchange_declare('analyze', AMQPExchangeType::DIRECT, false, true, false);
        $channel->queue_bind('analyze', 'analyze');


        $setName = $input->getArgument('set');
        $meta = json_decode(file_get_contents(__DIR__ . '/../../resources/Fixtures/Set/' . $setName . '/meta.json'), true);

        $numbers = $this->getPieceNumbers($meta);
        if (count($input->getArgument('pieceNumber')) > 0) {
            $numbers = array_map('intval', $input->getArgument('pieceNumber'));
        }

        $channel->queue_purge('analyze');
        foreach ($numbers as $pieceNumber) {
            $channel->basic_publish(new AMQPMessage(json_encode([
                'setName' => $setName,
                'pieceNumber' => (int) $pieceNumber
            ])), '', 'analyze');
        }

        $pieceCount = count($numbers);

        for ($i = 1; $i <= 10; $i++) {
            $process = new Process([ PHP_BINARY, __DIR__ . '/../../bin/console', 'app:consumer:piece:analyze']);
            $process->start(function (string $type, string $content) use ($output): void {
                $output->writeln(sprintf('[%s] %s', $type, $content));
            });
            $processes[] = $process;
        }

        $progress = new ProgressBar($output);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progress->start($pieceCount);
        do {
            [$queue, $messageCount, $consumerCount] = $channel->queue_declare('analyze', true);
            $progress->setProgress($pieceCount - $messageCount);

            usleep(100000);
        } while ($messageCount > 0);

        // Wait another 5 seconds because we only get the open message count, not containing the messages that are currently being processed
        sleep(5);

        $progress->finish();
        $output->writeln('');

        foreach ($processes as $process) {
            $process->stop();
        }

        $io->success('Finished.');

        return self::SUCCESS;
    }
}
