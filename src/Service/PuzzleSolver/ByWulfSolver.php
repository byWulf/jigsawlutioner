<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Context\SolutionReport;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\ReducedPiece;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\AddBestSinglePieceStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\CreateMissingGroupsStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\FillBlanksWithSinglePiecesStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\MergeGroupsStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\RemoveBadPiecesStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\RemoveSmallGroupsStrategy;
use Closure;

class ByWulfSolver implements PuzzleSolverInterface
{
    use ByWulfSolverTrait;

    public const DIRECTION_OFFSETS = [
        0 => ['x' => 0, 'y' => -1],
        1 => ['x' => -1, 'y' => 0],
        2 => ['x' => 0, 'y' => 1],
        3 => ['x' => 1, 'y' => 0],
    ];

    private ?Closure $stepProgressionCallback = null;

    private ?Closure $reportSolutionCallback = null;

    private ?SolutionReport $solutionReport = null;

    private AddBestSinglePieceStrategy $addBestSinglePieceStrategy;

    private MergeGroupsStrategy $mergeGroupsStrategy;

    private FillBlanksWithSinglePiecesStrategy $fillBlanksWithSinglePiecesStrategy;

    private RemoveBadPiecesStrategy $removeBadPiecesStrategy;

    private RemoveSmallGroupsStrategy $removeSmallGroupsStrategy;

    private CreateMissingGroupsStrategy $createMissingGroupsStrategy;

    public function __construct()
    {
        $this->addBestSinglePieceStrategy = new AddBestSinglePieceStrategy();
        $this->mergeGroupsStrategy = new MergeGroupsStrategy();
        $this->fillBlanksWithSinglePiecesStrategy = new FillBlanksWithSinglePiecesStrategy();
        $this->removeBadPiecesStrategy = new RemoveBadPiecesStrategy();
        $this->removeSmallGroupsStrategy = new RemoveSmallGroupsStrategy();
        $this->createMissingGroupsStrategy = new CreateMissingGroupsStrategy();
    }

    public function setStepProgressionCallback(Closure $stepProgressionCallback): void
    {
        $this->stepProgressionCallback = $stepProgressionCallback;
    }

    public function setReportSolutionCallback(Closure $reportSolutionCallback): void
    {
        $this->reportSolutionCallback = $reportSolutionCallback;
    }

    public function setSolutionReport(SolutionReport $solutionReport): void
    {
        $this->solutionReport = $solutionReport;
    }

    /**
     * @param array<int, Piece|ReducedPiece>                      $pieces
     * @param array<string, array<string, float>> $matchingMap
     *
     * @throws PuzzleSolverException
     */
    public function findSolution(array $pieces, array $matchingMap): Solution
    {
        $pieces = array_map(fn (Piece|ReducedPiece $piece): ReducedPiece => $piece instanceof Piece ? ReducedPiece::fromPiece($piece) : $piece, $pieces);

        $context = new ByWulfSolverContext(
            $pieces,
            $this->solutionReport?->getSolution() ?? new Solution(),
            $matchingMap,
            $this->stepProgressionCallback,
            $this->reportSolutionCallback,
            $this->solutionReport?->getSolutionStep() ?? 0,
            $this->solutionReport?->getRemovedMatchingKeys() ?? [],
        );

        $this->addBestSinglePieceStrategy->execute($context, 0.8, 0.5);
        $this->mergeGroupsStrategy->execute($context, 0.8);

        $this->addBestSinglePieceStrategy->execute($context, 0.6, 0.25);
        $this->mergeGroupsStrategy->execute($context, 0.6);

        $this->addBestSinglePieceStrategy->execute($context, 0.5, 0.1);
        $this->mergeGroupsStrategy->execute($context, 0.5);

        $this->addBestSinglePieceStrategy->execute($context, 0.01, 0.01);
        $this->mergeGroupsStrategy->execute($context, 0.01);

        $this->removeBadPiecesStrategy->execute($context, 0.5);

        $this->addBestSinglePieceStrategy->execute($context, 0.5, 0.1);
        $this->mergeGroupsStrategy->execute($context, 0.5);

        $this->removeBadPiecesStrategy->execute($context, 0.2);

        $this->repeatedlyAddPossiblePlacements($context, 0.01, 0.01);

        $this->removeBadPiecesStrategy->execute($context, 0.5);
        $this->repeatedlyAddPossiblePlacements($context, 0.01, 0.01);

        $this->repeatedlyAddPossiblePlacements($context, 0, 0);

        $context->setRemovingAllowed(false);
        $this->createMissingGroupsStrategy->execute($context);
        $this->repeatedlyAddPossiblePlacements($context, 0, 0);

        $this->createMissingGroupsStrategy->execute($context);
        $this->repeatedlyAddPossiblePlacements($context, 0, 0);

        $this->tryToAssignSinglePieces($context);

        $this->createMissingGroupsStrategy->execute($context);

        $this->outputProgress($context, 'We are done!');

        $groups = $context->getSolution()->getGroups();
        usort($groups, static fn (Group $a, Group $b): int => count($b->getPlacements()) <=> count($a->getPlacements()));
        $context->getSolution()->setGroups($groups);

        $this->setPlacementContexts($context->getSolution(), $context->getOriginalMatchingMap());

        $this->reportSolution($context);

        return $context->getSolution();
    }

    private function tryToAssignSinglePieces(ByWulfSolverContext $context): void
    {
        $biggestGroup = $context->getSolution()->getBiggestGroup();
        if ($biggestGroup === null) {
            return;
        }

        // We are perfectly finished, we don't have to do anything more
        if (count($biggestGroup->getPlacements()) === $context->getPiecesCount()) {
            return;
        }

        // Too bad performance, we better stop here to not waste more time
        if (count($biggestGroup->getPlacements()) < $context->getPiecesCount() * 0.9) {
            return;
        }

        $this->removeSmallGroupsStrategy->execute($context, $biggestGroup);

        // First try to assign all pieces that are still single to the biggest group
        if ($this->executeSinglePieceAssignment($context, null, null, false, 0)) {
            return;
        }

        // Then try to remove all bad connections and do it again
        if ($this->executeSinglePieceAssignment($context, 0.5, 2, false, 0)) {
            return;
        }

        // Now try to overwrite existing pieces if they fit there better
        if ($this->executeSinglePieceAssignment($context, null, null, true, 0)) {
            return;
        }

        // If there are still pieces missing, do it a few times with a bit of variance
        for ($i = 0; $i < 15; ++$i) {
            if ($this->executeSinglePieceAssignment($context, 0.5, 2, false, 0.2)) {
                return;
            }
        }
    }

    /**
     * @
     */
    private function executeSinglePieceAssignment(ByWulfSolverContext $context, ?float $removeMaxProbability, ?int $removeMinimumSidesBelow, bool $canPlaceAboveExistingPlacement, float $variationFactor): bool
    {
        if ($removeMaxProbability !== null && $removeMinimumSidesBelow !== null) {
            $this->removeBadPiecesStrategy->execute($context, 0.5, 2);
        }

        $biggestGroup = $context->getSolution()->getBiggestGroup();
        if ($biggestGroup === null) {
            return true;
        }

        if ($canPlaceAboveExistingPlacement) {
            $this->createMissingGroupsStrategy->execute($context);
            $this->fillBlanksWithSinglePiecesStrategy->execute($context, $biggestGroup, true, $variationFactor);
        }

        $this->createMissingGroupsStrategy->execute($context);
        $this->fillBlanksWithSinglePiecesStrategy->execute($context, $biggestGroup, false, $variationFactor);

        return count($biggestGroup->getPlacements()) === $context->getPiecesCount();
    }

    /**
     * @throws PuzzleSolverException
     */
    private function repeatedlyAddPossiblePlacements(ByWulfSolverContext $context, float $minProbability, float $minDifference): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $context->resetMatchingMap();
            $this->addBestSinglePieceStrategy->execute($context, $minProbability, $minDifference);
            $this->mergeGroupsStrategy->execute($context, $minProbability);
        }
    }

    /**
     * @param array<string, array<string, float>> $matchingMap
     */
    private function setPlacementContexts(Solution $solution, array $matchingMap): void
    {
        foreach ($solution->getGroups() as $group) {
            foreach ($group->getPlacements() as $placement) {
                $context = [];
                foreach (self::DIRECTION_OFFSETS as $indexOffset => $positionOffset) {
                    $sideKey = $this->getKey($placement->getPiece()->getIndex(), $placement->getTopSideIndex() + $indexOffset);

                    $matchedPlacement = $group->getPlacementByPosition($placement->getX() + $positionOffset['x'], $placement->getY() + $positionOffset['y']);
                    $matchedSideKey = null;
                    if ($matchedPlacement !== null) {
                        $matchedSideKey = $this->getKey($matchedPlacement->getPiece()->getIndex(), $matchedPlacement->getTopSideIndex() + 6 + $indexOffset);
                    }

                    $context[$indexOffset] = [
                        'probabilities' => $matchingMap[$sideKey] ?? [],
                        'matchedProbabilityIndex' => $matchedSideKey !== null ? array_search($matchedSideKey, array_keys($matchingMap[$sideKey] ?? []), true) : null,
                        'matchingKey' => $sideKey . '-' . $matchedSideKey,
                    ];
                }
                $placement->setContext($context);
            }
        }
    }
}
