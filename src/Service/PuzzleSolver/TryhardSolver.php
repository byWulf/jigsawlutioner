<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service\PuzzleSolver;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Bywulf\Jigsawlutioner\Exception\SideClassifierException;
use Bywulf\Jigsawlutioner\Service\SideMatcher\SideMatcherInterface;
use Bywulf\Jigsawlutioner\Service\SolutionOutputter;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class TryhardSolver implements PuzzleSolverInterface
{
    private const DIRECTION_OFFSETS = [
        0 => ['x' => 0, 'y' => -1],
        1 => ['x' => -1, 'y' => 0],
        2 => ['x' => 0, 'y' => 1],
        3 => ['x' => 1, 'y' => 0],
    ];

    /**
     * @var null|Placement[][]
     */
    private ?array $bestPlacements = null;
    private ?float $bestPlacementPerformance = null;

    private int $currentDepth = 0;

    private array $matchingMap;

    private FilesystemAdapter $cache;
    private string $cacheName;

    private int $maxNopX = 0;
    private int $maxNopY = 0;
    private ?int $maxBorderX = null;
    private ?int $maxBorderY = null;
    private int $maxX = 0;
    private int $maxY = 0;
    private int $count;

    public function __construct(
        private SideMatcherInterface $sideMatcher,
        private ?LoggerInterface $logger = null
    ) {
        $this->cache = new FilesystemAdapter(directory: __DIR__ . '/../../../resources/cache');
    }

    /**
     * @param Piece[] $pieces
     * @throws \Psr\Cache\InvalidArgumentException|SideClassifierException
     */
    public function findSolution(array $pieces, string $cacheName = null, bool $useCache = true): Solution
    {
        if ($cacheName === null) {
            throw new InvalidArgumentException('$cacheName has to be given.');
        }
        $this->cacheName = $cacheName;

        if (!$useCache) {
            $this->cache->delete('matchingMap_' . $cacheName);
            $this->cache->commit();
        }

        $this->matchingMap = $this->cache->get(sha1(__CLASS__ . '::matchingMap_' . $cacheName), function() use ($pieces) {
            $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Creating matching probability map...');
            return $this->getMatchingMap($pieces);
        });

        foreach ($this->matchingMap as &$oppositeSides) {
            $oppositeSides = array_filter($oppositeSides, static fn (float $probability): bool => $probability >= 0.7);
        }
        unset($oppositeSides);
        $this->matchingMap = array_filter($this->matchingMap, static fn (array $probabilities): bool => count($probabilities) > 0);

        $this->count = count($pieces);

        // if there are corner pieces, only try the corner pieces rotated to the top left corner
        if (count($this->getCornerPieces($pieces)) > 0) {
            foreach ($this->getCornerPieces($pieces) as $piece) {
                foreach ([0, 1, 2, 3] as $topSideIndex) {
                    if (
                        $piece->getSide($topSideIndex)->getDirection() === DirectionClassifier::NOP_STRAIGHT &&
                        $piece->getSide($topSideIndex + 1)->getDirection() === DirectionClassifier::NOP_STRAIGHT
                    ) {
                        $this->addPlacement([], $pieces, 0, 0, $piece, $topSideIndex);
                    }
                }
            }
        // Otherwise try all pieces in all directions
        } else {
            foreach ($pieces as $piece) {
                foreach ([0, 1, 2, 3] as $topSideIndex) {
                    $this->addPlacement([], $pieces, 0, 0, $piece, $topSideIndex);
                }
            }
        }

        $this->logger?->info((new DateTimeImmutable())->format('Y-m-d H:i:s') . ' - Finished searching for best solution.');

        $group = new Group();
        foreach ($this->bestPlacements as $horizontalPlacements) {
            foreach ($horizontalPlacements as $placement) {
                $group->addPlacement($placement);
            }
        }

        return new Solution([$group]);
    }

    /**
     * @throws SideClassifierException
     */
    private function addPlacement(array $placementArray, array $pieces, int $x, int $y, Piece $piece, int $topSideIndex): void
    {
        $this->logger?->debug(str_repeat('.', $this->currentDepth) . 'addPlacement(' . $x . ', ' . $y . ', ' . $piece->getIndex() . ', ' . $topSideIndex . ')');
        $this->currentDepth++;

        $index = array_search($piece, $pieces, true);
        unset($pieces[$index]);

        $placementArray[$y][$x] = new Placement($x, $y, $piece, $topSideIndex);

        $previousMaxX = $this->maxX;
        $previousMaxY = $this->maxY;
        $previousMaxBorderX = $this->maxBorderX;
        $previousMaxBorderY = $this->maxBorderY;
        $previousMaxNopX = $this->maxNopX;
        $previousMaxNopY = $this->maxNopY;

        if ($x > $this->maxX) {
            $this->maxX = $x;
        }
        if ($y > $this->maxY) {
            $this->maxY = $y;
        }
        if (($this->maxBorderY === null || $y > $this->maxBorderY) && $piece->getSide($topSideIndex + 2)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            $this->maxBorderY = $y;
        }
        if (($this->maxBorderX === null || $x > $this->maxBorderX) && $piece->getSide($topSideIndex + 3)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            $this->maxBorderX = $x;
        }

        if ($y > $this->maxNopY && $piece->getSide($topSideIndex + 2)->getDirection() !== DirectionClassifier::NOP_STRAIGHT) {
            $this->maxNopY = $y;
        }
        if ($x > $this->maxNopX && $piece->getSide($topSideIndex + 3)->getDirection() !== DirectionClassifier::NOP_STRAIGHT) {
            $this->maxNopX = $x;
        }

        $foundFittingPiece = false;

        // Look, if we can go to the left
        if (!isset($placementArray[$y][$x - 1])) {
            foreach ($this->getConnectingKeys($piece, $topSideIndex + 1) as $connectingKey) {
                $nextPiece = $pieces[$this->getPieceIndexFromKey($connectingKey)] ?? null;
                $nextTopSideIndex = ($this->getSideIndexFromKey($connectingKey) + 1) % 4;
                if ($nextPiece !== null && $this->isFitting($placementArray, $x - 1, $y, $nextPiece, $nextTopSideIndex)) {
                    $foundFittingPiece = true;
                    $this->addPlacement($placementArray, $pieces, $x - 1, $y, $nextPiece, $nextTopSideIndex);
                }
            }
        }

        // Look, if we can go to the right
        if (!isset($placementArray[$y][$x + 1])) {
            foreach ($this->getConnectingKeys($piece, $topSideIndex + 3) as $connectingKey) {
                $nextPiece = $pieces[$this->getPieceIndexFromKey($connectingKey)] ?? null;
                $nextTopSideIndex = ($this->getSideIndexFromKey($connectingKey) + 3) % 4;
                if ($nextPiece !== null && $this->isFitting($placementArray, $x + 1, $y, $nextPiece, $nextTopSideIndex)) {
                    $foundFittingPiece = true;
                    $this->addPlacement($placementArray, $pieces, $x + 1, $y, $nextPiece, $nextTopSideIndex);
                }
            }
        }

        // Also try to go to the top and bottom and start a new line
        if (!isset($placementArray[$y - 1][$x])) {
            foreach ($this->getConnectingKeys($piece, $topSideIndex) as $connectingKey) {
                $nextPiece = $pieces[$this->getPieceIndexFromKey($connectingKey)] ?? null;
                $nextTopSideIndex = ($this->getSideIndexFromKey($connectingKey) + 2) % 4;
                if ($nextPiece !== null && $this->isFitting($placementArray, $x, $y - 1, $nextPiece, $nextTopSideIndex)) {
                    $foundFittingPiece = true;
                    $this->addPlacement($placementArray, $pieces, $x, $y - 1, $nextPiece, $nextTopSideIndex);
                }
            }
        }

        if (!isset($placementArray[$y + 1][$x])) {
            foreach ($this->getConnectingKeys($piece, $topSideIndex + 2) as $connectingKey) {
                $nextPiece = $pieces[$this->getPieceIndexFromKey($connectingKey)] ?? null;
                $nextTopSideIndex = $this->getSideIndexFromKey($connectingKey);
                if ($nextPiece !== null && $this->isFitting($placementArray, $x, $y + 1, $nextPiece, $nextTopSideIndex)) {
                    $foundFittingPiece = true;
                    $this->addPlacement($placementArray, $pieces, $x, $y + 1, $nextPiece, $nextTopSideIndex);
                }
            }
        }

        // If nothing could be placed anymore, this solution is done and we calculate the performance
        if ($foundFittingPiece === false) {
            $performance = $this->calculatePerformance($placementArray);
            $this->logger?->info(str_repeat('.', $this->currentDepth) . ' => FINISHED. Performance: ' . $performance);

            if ($this->bestPlacementPerformance === null || $performance > $this->bestPlacementPerformance) {
                $this->bestPlacementPerformance = $performance;
                $this->bestPlacements = $placementArray;
                $this->logger?->notice('FOUND BETTER PERFORMANCE: ' . $performance);

                $this->saveSolutionToFile();
            }
        }


        $this->maxX = $previousMaxX;
        $this->maxY = $previousMaxY;
        $this->maxBorderX = $previousMaxBorderX;
        $this->maxBorderY = $previousMaxBorderY;
        $this->maxNopX = $previousMaxNopX;
        $this->maxNopY = $previousMaxNopY;

        $this->currentDepth--;
    }

    /**
     * @throws SideClassifierException
     */
    private function isFitting(array $placementArray, int $x, int $y, Piece $piece, int $topSideIndex): bool
    {
        if (isset($placementArray[$y][$x])) {
            return false;
        }

        // straight would get placed inside current constraint
        if ($y > 0 && $piece->getSide($topSideIndex)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($x > 0 && $piece->getSide($topSideIndex + 1)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($y < $this->maxBorderY && $piece->getSide($topSideIndex + 2)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($x < $this->maxBorderX && $piece->getSide($topSideIndex + 3)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }

        // Piece would get placed outside current constraint
        if ($y < 0) {
            return false;
        }
        if ($x < 0) {
            return false;
        }
        if ($this->maxBorderY !== null && $y > $this->maxBorderY) {
            return false;
        }
        if ($this->maxBorderX !== null && $x > $this->maxBorderX) {
            return false;
        }

        // non-straight side would get placed on border of current constraint
        if ($y === 0 && $piece->getSide($topSideIndex)->getDirection() !== DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($x === 0 && $piece->getSide($topSideIndex + 1)->getDirection() !== DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($this->maxBorderY !== null && $y === $this->maxBorderY && $piece->getSide($topSideIndex + 2)->getDirection() !== DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($this->maxBorderX !== null && $x === $this->maxBorderX && $piece->getSide($topSideIndex + 3)->getDirection() !== DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }

        // straight side would get placed on non-constraining border
        if ($y === $this->maxNopY && $piece->getSide($topSideIndex + 2)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }
        if ($x === $this->maxNopX && $piece->getSide($topSideIndex + 3)->getDirection() === DirectionClassifier::NOP_STRAIGHT) {
            return false;
        }

        // Dimensions of constraint would get abnormal off
        $minWidth = sqrt($this->count) * 0.8;
        $maxWidth = sqrt($this->count) * 1.2;
        if ($y > $maxWidth || ($y < $minWidth && $piece->getSide($topSideIndex + 2)->getDirection() === DirectionClassifier::NOP_STRAIGHT)) {
            return false;
        }
        if ($x > $maxWidth || ($x < $minWidth && $piece->getSide($topSideIndex + 3)->getDirection() === DirectionClassifier::NOP_STRAIGHT)) {
            return false;
        }

        $foundConnectingBorder = false;
        foreach (self::DIRECTION_OFFSETS as $indexOffset => $positionOffset) {
            if (!isset($placementArray[$y + $positionOffset['y']][$x + $positionOffset['x']])) {
                continue;
            }

            $placement = $placementArray[$y + $positionOffset['y']][$x + $positionOffset['x']];
            $key = $this->getKey($piece->getIndex(), ($indexOffset + $topSideIndex) % 4);
            $comparingKey = $this->getKey($placement->getPiece()->getIndex(), ($indexOffset + $placement->getTopSideIndex() + 2) % 4);

            if (!isset($this->matchingMap[$key][$comparingKey])) {
                return false;
            }

            $foundConnectingBorder = true;
        }

        return $foundConnectingBorder;
    }

    private function calculatePerformance(array $placementArray): float
    {
        $matchingSum = 0;
        foreach ($placementArray as $y => $horizontalPlacements) {
            foreach ($horizontalPlacements as $x => $placement) {
                foreach (self::DIRECTION_OFFSETS as $indexOffset => $positionOffset) {
                    if (isset($placementArray[$y + $positionOffset['y']][$x + $positionOffset['x']])) {
                        $comparingPlacement = $placementArray[$y + $positionOffset['y']][$x + $positionOffset['x']];
                        $key = $this->getKey($placement->getPiece()->getIndex(), ($placement->getTopSideIndex() + $indexOffset) % 4);
                        $comparingKey = $this->getKey($comparingPlacement->getPiece()->getIndex(), ($indexOffset + $comparingPlacement->getTopSideIndex() + 2) % 4);

                        $matchingSum += $this->matchingMap[$key][$comparingKey] ?? 0;
                    }
                }
            }
        }

        return $matchingSum;
    }

    /**
     * @param Piece[] $pieces
     *
     * @return float[][]
     */
    private function getMatchingMap(array $pieces): array
    {
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

                // Remove own sides from map, because the puzzle must not be matched with itself
                for ($i = 0; $i < 4; $i++) {
                    unset($probabilities[$this->getKey($pieceIndex, $i)]);
                }

                // Only keep the best 2 sides, because our average correct position is 2.75
                $probabilities = array_slice($probabilities, 0, 1);

                $matchingMap[$this->getKey($pieceIndex, $sideIndex)] = $probabilities;
            }
        }

        return $matchingMap;
    }

    /**
     * @param Piece[] $pieces
     * @return Piece[]
     */
    private function getCornerPieces(array $pieces): array
    {
        return array_filter(
            $pieces,
            static fn (Piece $piece): bool => count(array_filter(
                $piece->getSides(),
                static fn (Side $side): bool => $side->getDirection() === DirectionClassifier::NOP_STRAIGHT
            )) === 2
        );
    }

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

    private function getConnectingKeys(Piece $piece, int $sideIndex): array
    {
        return array_keys($this->matchingMap[$this->getKey($piece->getIndex(), $sideIndex)] ?? []);
    }

    /**
     * @return void
     */
    private function saveSolutionToFile(): void
    {
        $solutionOutputter = new SolutionOutputter();

        $group = new Group();
        foreach ($this->bestPlacements as $horizontalPlacements) {
            foreach ($horizontalPlacements as $placement) {
                $group->addPlacement($placement);
            }
        }

        $solution = new Solution([$group]);

        $htmlFile = __DIR__ . '/../../../resources/Fixtures/Set/' . $this->cacheName . '/solution.html';
        $solutionOutputter->outputAsHtml(
            $solution,
            $htmlFile,
            __DIR__ . '/../../../resources/Fixtures/Set/' . $this->cacheName . '/piece%s_transparent.png'
        );
    }
}
