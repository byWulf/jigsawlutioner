<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;

class CreateMissingGroupsStrategy
{
    use ByWulfSolverTrait;

    public function execute(ByWulfSolverContext $context): void
    {
        foreach ($context->getPieces() as $piece) {
            if ($context->getSolution()->getGroupByPiece($piece)) {
                continue;
            }

            $group = new Group();
            $group->addPlacement(new Placement(0, 0, $piece, 0));
            $context->getSolution()->addGroup($group);

            $this->outputProgress($context, 'Creating single groups of all pieces that were not yet placed...');
        }
    }
}
