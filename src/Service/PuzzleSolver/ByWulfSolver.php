<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Context\ByWulfSolverContext;
use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Exception\PuzzleSolverException;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\ByWulfSolverTrait;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\AddBestSinglePieceStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\CreateMissingGroupsStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\FillBlanksWithSinglePiecesStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\MergeGroupsStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\RemoveBadPiecesStrategy;
use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver\Strategy\RemoveSmallGroupsStrategy;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class ByWulfSolver implements PuzzleSolverInterface
{
    use ByWulfSolverTrait;

    public const DIRECTION_OFFSETS = [
        0 => ['x' => 0, 'y' => -1],
        1 => ['x' => -1, 'y' => 0],
        2 => ['x' => 0, 'y' => 1],
        3 => ['x' => 1, 'y' => 0],
    ];

    private FilesystemAdapter $cache;

    private bool $allowRemovingWhileMerging = true;

    private bool $outputStepSaving = false;
    private AddBestSinglePieceStrategy $addBestSinglePieceStrategy;
    private MergeGroupsStrategy $mergeGroupsStrategy;
    private FillBlanksWithSinglePiecesStrategy $fillBlanksWithSinglePiecesStrategy;
    private RemoveBadPiecesStrategy $removeBadPiecesStrategy;
    private RemoveSmallGroupsStrategy $removeSmallGroupsStrategy;
    private CreateMissingGroupsStrategy $createMissingGroupsStrategy;

    public function __construct(
        private SideMatcherInterface $sideMatcher,
        private ?LoggerInterface $logger = null
    ) {
        $this->cache = new FilesystemAdapter(directory: __DIR__ . '/../../../resources/cache');

        $this->addBestSinglePieceStrategy = new AddBestSinglePieceStrategy($this->logger);
        $this->mergeGroupsStrategy = new MergeGroupsStrategy($this->logger);
        $this->fillBlanksWithSinglePiecesStrategy = new FillBlanksWithSinglePiecesStrategy($this->logger);
        $this->removeBadPiecesStrategy = new RemoveBadPiecesStrategy($this->logger);
        $this->removeSmallGroupsStrategy = new RemoveSmallGroupsStrategy($this->logger);
        $this->createMissingGroupsStrategy = new CreateMissingGroupsStrategy($this->logger);
    }

    /**
     * @param Piece[] $pieces
     * @throws PuzzleSolverException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function findSolution(array $pieces, string $cacheName = null, bool $useCache = true, Closure $stepProgression = null): Solution
    {
        $context = new ByWulfSolverContext(
            $pieces,
            $this->getMatchingMap($pieces, $cacheName, $useCache),
            $stepProgression
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

        if (count($context->getSolution()->getBiggestGroup()?->getPlacements() ?? []) < $context->getPiecesCount() * 0.8) {
            $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Because we don\'t have a big group yet, we try a bit more...');
            $this->removeBadPiecesStrategy->execute($context, 0.5);
            $this->repeatedlyAddPossiblePlacements($context, 0.01, 0.01);
        }

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
        usort($groups, static fn(Group $a, Group $b): int => count($b->getPlacements()) <=> count($a->getPlacements()));
        $context->getSolution()->setGroups($groups);

        $this->setPlacementContexts($context->getSolution(), $context->getOriginalMatchingMap());

        return $context->getSolution();
    }

    private function tryToAssignSinglePieces(ByWulfSolverContext $context): void {
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

        // First try to assign all pieces that are still single to the biggest group
        $this->removeSmallGroupsStrategy->execute($context, $biggestGroup);
        $this->createMissingGroupsStrategy->execute($context);
        $this->fillBlanksWithSinglePiecesStrategy->execute($context, $biggestGroup);

        if (count($biggestGroup->getPlacements()) === $context->getPiecesCount()) {
            return;
        }

        // Then try to remove all bad connections and do it again
        $this->removeBadPiecesStrategy->execute($context, 0.5, 2);
        $this->createMissingGroupsStrategy->execute($context);
        $this->fillBlanksWithSinglePiecesStrategy->execute($context, $biggestGroup);

        if (count($biggestGroup->getPlacements()) === $context->getPiecesCount()) {
            return;
        }

        // If there are still pieces missing, do it a few times with a bit of variance
        for ($i = 0; $i < 5; $i++) {
            $this->removeBadPiecesStrategy->execute($context, 0.5, 2);
            $this->createMissingGroupsStrategy->execute($context);
            $this->fillBlanksWithSinglePiecesStrategy->execute($context, $biggestGroup, 0.2);
        }
    }

    /**
     * @throws PuzzleSolverException
     */
    private function repeatedlyAddPossiblePlacements(ByWulfSolverContext $context, float $minProbability, float $minDifference): void {
        $lastPieceCount = $context->getSolution()->getPieceCount();
        $lastGroupCount = count($context->getSolution()->getGroups());
        for ($i = 0; $i < 5; $i++) {
            $context->setMatchingMap($context->getOriginalMatchingMap());
            $this->addBestSinglePieceStrategy->execute($context, $minProbability, $minDifference);
            $this->mergeGroupsStrategy->execute($context, $minProbability);

            if ($context->getSolution()->getPieceCount() === $lastPieceCount && count($context->getSolution()->getGroups()) === $lastGroupCount) {
                break;
            }
            $lastPieceCount = $context->getSolution()->getPieceCount();
            $lastGroupCount = count($context->getSolution()->getGroups());
        }
    }

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

    /**
     * @param Piece[] $pieces
     *
     * @return float[][]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function getMatchingMap(array $pieces, ?string $cacheName, bool $useCache): array
    {
        if ($cacheName === null) {
            throw new InvalidArgumentException('$cacheName has to be given.');
        }

        $cacheKey = sha1(__CLASS__ . '::matchingMap_' . $cacheName);
        if (!$useCache) {
            $this->cache->delete($cacheKey);
            $this->cache->commit();
        }

        return $this->cache->get($cacheKey, function() use ($pieces) {
            $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Creating matching probability map...');

            $matchingMap = [];

            $allSides = [];
            foreach ($pieces as $pieceIndex => $piece) {
                foreach ($piece->getSides() as $sideIndex => $side) {
                    $allSides[$this->getKey($pieceIndex, $sideIndex)] = $side;
                }
            }

            foreach ($pieces as $pieceIndex => $piece) {
                foreach ($piece->getSides() as $sideIndex => $side) {
                    $probabilities = $this->sideMatcher->getMatchingProbabilities($side, $allSides);
                    arsort($probabilities);
                    $matchingMap[$this->getKey($pieceIndex, $sideIndex)] = $probabilities;

                    // Remove own sides from map, because the puzzle must not be matched with itself
                    for ($i = 0; $i < 4; $i++) {
                        unset($matchingMap[$this->getKey($pieceIndex, $sideIndex)][$this->getKey($pieceIndex, $i)]);
                    }
                }
            }

            return $matchingMap;
        });
    }
}
