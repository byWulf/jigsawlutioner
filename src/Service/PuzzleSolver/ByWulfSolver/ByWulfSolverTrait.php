<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\Validator\Group\PossibleSideMatching;
use Bywulf\Jigsawlutioner\Validator\Group\RealisticSide;
use Bywulf\Jigsawlutioner\Validator\Group\RectangleGroup;
use Bywulf\Jigsawlutioner\Validator\Group\UniquePlacement;

trait ByWulfSolverTrait
{
    private function getKey(int $pieceNumber, int $sideIndex): string
    {
        return $pieceNumber . '_' . (($sideIndex + 4) % 4);
    }

    private function getPieceIndexFromKey(string $key): int
    {
        return (int) explode('_', $key)[0];
    }

    private function getSideIndexFromKey(string $key): int
    {
        return (int) explode('_', $key)[1];
    }

    private function isGroupValid(Group $group, int $maxAllowedDoubles, int $piecesCount): bool
    {
        try {
            $this->validator->validate($group, [
                new UniquePlacement(['maxAllowedDoubles' => $maxAllowedDoubles]),
                new RealisticSide(['piecesCount' => $piecesCount]),
                new RectangleGroup(),
                new PossibleSideMatching(),
            ]);

            return true;
        } catch (GroupInvalidException) {
            return false;
        }
    }

    private function outputProgress(ByWulfSolverContext $context, string $description): void
    {
        if ($context->getStepProgression() !== null) {
            $context->getStepProgression()(
                $description,
                count($context->getSolution()->getGroups()) + ($context->getPiecesCount() - $context->getSolution()->getPieceCount()),
                count($context->getSolution()->getBiggestGroup()?->getPlacements() ?? [])
            );
        }
    }
}
