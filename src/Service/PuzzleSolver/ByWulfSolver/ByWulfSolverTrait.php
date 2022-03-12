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
use DateTimeImmutable;

trait ByWulfSolverTrait
{
    private function getKey(int|string $pieceIndex, int $sideIndex): string
    {
        return $pieceIndex . '_' . (($sideIndex + 4) % 4);
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

    private function outputProgress(ByWulfSolverContext $context, string $step): void
    {
        $placedPieces = $context->getSolution()->getPieceCount();
        $createdGroups = count($context->getSolution()->getGroups());
        $piecesInBiggestGroup = count($context->getSolution()->getBiggestGroup()?->getPlacements() ?? []);

        $this->logger?->debug(sprintf(
            '%s - Placed %s pieces in %s groups by step %s. Biggest groups has %s pieces.',
            (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            $placedPieces,
            $createdGroups,
            $step,
            $piecesInBiggestGroup
        ));

        if ($context->getStepProgression() !== null) {
            $context->getStepProgression()(
                $placedPieces,
                $createdGroups,
                $piecesInBiggestGroup
            );
        }
    }
}
