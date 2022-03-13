<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;

class RemoveSmallGroupsStrategy
{
    use ByWulfSolverTrait;

    public function execute(ByWulfSolverContext $context, Group $biggestGroup): void
    {
        foreach ($context->getSolution()->getGroups() as $group) {
            if ($group === $biggestGroup) {
                continue;
            }

            $context->getSolution()->removeGroup($group);

            $this->outputProgress($context, 'Splitting up all groups that are not the biggest group into single pieces...');
        }
    }
}
