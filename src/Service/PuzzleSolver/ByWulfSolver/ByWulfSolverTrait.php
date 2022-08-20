<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Context\SolutionReport;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\Validator\Group\PossibleSideMatching;
use Bywulf\Jigsawlutioner\Validator\Group\RealisticSize;
use Bywulf\Jigsawlutioner\Validator\Group\RectangleGroup;
use Bywulf\Jigsawlutioner\Validator\Group\UniquePlacement;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait ByWulfSolverTrait
{
    protected ?ValidatorInterface $validator = null;

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
        if ($this->validator === null) {
            $this->validator = Validation::createValidator();
        }

        try {
            $this->validator->validate($group, [
                new UniquePlacement(['maxAllowedDoubles' => $maxAllowedDoubles]),
                new RealisticSize(['piecesCount' => $piecesCount]),
                new RectangleGroup(),
                new PossibleSideMatching(),
            ]);

            return true;
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (GroupInvalidException) {
            return false;
        }
    }

    private function isValidRectangle(Group $group): bool
    {
        if ($this->validator === null) {
            $this->validator = Validation::createValidator();
        }

        try {
            $this->validator->validate($group, [
                new RectangleGroup(),
            ]);

            return true;
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (GroupInvalidException) {
            return false;
        }
    }

    private function outputProgress(ByWulfSolverContext $context, string $description): void
    {
        if ($context->getStepProgression() === null) {
            return;
        }

        $context->getStepProgression()(
            $context->getCurrentSolutionStep() . ' - ' . $description,
            count($context->getSolution()->getGroups()) + ($context->getPiecesCount() - $context->getSolution()->getPieceCount()),
            count($context->getSolution()->getBiggestGroup()?->getPlacements() ?? [])
        );
    }

    private function shouldSkipStep(ByWulfSolverContext $context): bool
    {
        if ($context->getCurrentSolutionStep() < $context->getStartFromSolutionStep()) {
            $context->increaseCurrentSolutionStep();

            return true;
        }

        return false;
    }

    private function reportSolution(ByWulfSolverContext $context): void
    {
        $context->increaseCurrentSolutionStep();

        if ($context->getSolutionReporter() !== null) {
            $context->getSolutionReporter()(new SolutionReport($context->getCurrentSolutionStep(), $context->getSolution(), $context->getRemovedMatchingKeys()));
        }
    }
}
