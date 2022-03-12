<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AddBestSinglePieceStrategy
{
    use ByWulfSolverTrait;

    private ValidatorInterface $validator;

    public function __construct()
    {
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->validator = Validation::createValidator();
    }

    /**
     * @throws PuzzleSolverException
     */
    public function execute(ByWulfSolverContext $context, float $minProbability, float $minDifference): void
    {
        $outputMessage = 'Adding new pieces with minProbability of ' . $minProbability . '/' . $minDifference . '...';
        $this->outputProgress($context, $outputMessage);

        while ($this->addNextPlacement($context, $minProbability, $minDifference)) {
            $this->outputProgress($context, $outputMessage);
        }
    }

    /**
     * @throws PuzzleSolverException
     */
    private function addNextPlacement(ByWulfSolverContext $context, float $minProbability, float $minDifference): bool
    {
        $nextKey = $this->getMostFittableSide($context, $minProbability, $minDifference);
        if ($nextKey === null) {
            return false;
        }

        $nextPieceIndex = $this->getPieceIndexFromKey($nextKey);

        $matchingKey = array_key_first($context->getMatchingProbabilities($nextKey));
        $matchingPieceIndex = $this->getPieceIndexFromKey($matchingKey);

        $existingGroup = null;
        foreach ($context->getSolution()->getGroups() as $group) {
            if ($group->getPlacementByPiece($context->getPiece($matchingPieceIndex))) {
                $existingGroup = $group;
                break;
            }
        }
        if ($existingGroup === null) {
            $existingGroup = new Group();
            $context->getSolution()->addGroup($existingGroup);
            $existingGroup->addPlacement(new Placement(0, 0, $context->getPiece($nextPieceIndex), 0));
        }

        $this->addPlacementToGroup($context, $existingGroup, $nextKey, $matchingKey);

        $context->unsetMatchingMapKey($nextKey);
        $context->unsetMatchingMapKey($matchingKey);

        return true;
    }

    private function getMostFittableSide(ByWulfSolverContext $context, float $minProbability, float $minDifference): ?string
    {
        $bestRating = 0;
        $bestKey = null;
        foreach ($context->getMatchingMap() as $key => $probabilities) {
            if ($context->getSolution()->getGroupByPiece($context->getPiece($this->getPieceIndexFromKey($key)))) {
                continue;
            }

            [$bestProbability, $secondProbability] = array_slice(array_values($context->getMatchingProbabilities($key)), 0, 2);
            $rating = ($bestProbability - $secondProbability) * ($bestProbability ** 3);
            if ($bestProbability >= $minProbability && ($bestProbability - $secondProbability) >= $minDifference && $rating > $bestRating) {
                $bestRating = $rating;
                $bestKey = $key;
            }
        }

        return $bestKey;
    }

    /**
     * @throws PuzzleSolverException
     */
    private function addPlacementToGroup(ByWulfSolverContext $context, Group $group, string $key1, string $key2): void
    {
        $placement1 = $group->getPlacementByPiece($context->getPiece($this->getPieceIndexFromKey($key1)));
        $placement2 = $group->getPlacementByPiece($context->getPiece($this->getPieceIndexFromKey($key2)));

        if ($placement1 !== null && $placement2 === null) {
            $existingPlacement = $placement1;
            $existingSideIndex = $this->getSideIndexFromKey($key1);

            $newPiece = $context->getPiece($this->getPieceIndexFromKey($key2));
            $newSideIndex = $this->getSideIndexFromKey($key2);
        } elseif ($placement2 !== null && $placement1 === null) {
            $existingPlacement = $placement2;
            $existingSideIndex = $this->getSideIndexFromKey($key2);

            $newPiece = $context->getPiece($this->getPieceIndexFromKey($key1));
            $newSideIndex = $this->getSideIndexFromKey($key1);
        } else {
            throw new PuzzleSolverException('Trying to place a piece to an existing group, but something strange happened in the data.');
        }

        $placement = new Placement(
            $existingPlacement->getX() + ByWulfSolver::DIRECTION_OFFSETS[($existingSideIndex - $existingPlacement->getTopSideIndex() + 4) % 4]['x'],
            $existingPlacement->getY() + ByWulfSolver::DIRECTION_OFFSETS[($existingSideIndex - $existingPlacement->getTopSideIndex() + 4) % 4]['y'],
            $newPiece,
            (($newSideIndex - $existingSideIndex + $existingPlacement->getTopSideIndex() + 2) + 4) % 4
        );

        $group->addPlacement($placement);

        if (!$this->isGroupValid($group, 0, $context->getPiecesCount())) {
            // Because group is not valid now, we reset it and do nothing
            $group->removePlacement($placement);
        }
    }
}
