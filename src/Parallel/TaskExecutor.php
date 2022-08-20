<?php

namespace Bywulf\Jigsawlutioner\Parallel;

use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Task;
use function Amp\call;
use function Amp\Promise\all;

class TaskExecutor
{
    /**
     * @param array<Task> $tasks
     * @return array<mixed>
     */
    public function execute(array $tasks, int $parallelTasks = 10): array
    {
        $results = [];

        Loop::run(function () use (&$results, $tasks, $parallelTasks) {
            $pool = new DefaultPool($parallelTasks);

            $coroutines = [];
            foreach ($tasks as $task) {
                $coroutines[] = call(fn() => yield $pool->enqueue($task));
            }

            $results = yield all($coroutines);

            return yield $pool->shutdown();
        });

        return $results;
    }
}
