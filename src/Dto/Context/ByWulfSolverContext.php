<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto\Context;

use Bywulf\Jigsawlutioner\Dto\ReducedPiece;
use Bywulf\Jigsawlutioner\Dto\Solution;
use Closure;
use InvalidArgumentException;

class ByWulfSolverContext
{
    /**
     * @var ReducedPiece[]
     */
    private array $pieces = [];

    private Solution $solution;

    private int $piecesCount;

    /**
     * @var array<string, array<string, float>>
     */
    private array $matchingMap;

    private bool $removingAllowed = true;

    private int $currentSolutionStep = 0;

    /**
     * @param ReducedPiece[]                      $pieces
     * @param array<string, array<string, float>> $originalMatchingMap
     */
    public function __construct(
        array $pieces,
        private readonly array $originalMatchingMap,
        private readonly ?Closure $stepProgression = null,
        private readonly ?Closure $solutionReporter = null,
        private readonly int $startFromSolutionStep = 0,
    ) {
        foreach ($pieces as $piece) {
            $this->pieces[$piece->getIndex()] = $piece;
        }

        $this->solution = new Solution();
        $this->piecesCount = count($this->pieces);
        $this->matchingMap = $this->originalMatchingMap;
    }

    public function getSolution(): Solution
    {
        return $this->solution;
    }

    public function setSolution(Solution $solution): void
    {
        $this->solution = $solution;
    }

    public function getCurrentSolutionStep(): int
    {
        return $this->currentSolutionStep;
    }

    public function increaseCurrentSolutionStep(): void
    {
        $this->currentSolutionStep++;
    }

    public function getStartFromSolutionStep(): int
    {
        return $this->startFromSolutionStep;
    }

    /**
     * @return ReducedPiece[]
     */
    public function getPieces(): array
    {
        return $this->pieces;
    }

    public function getPiece(int $pieceNumber): ReducedPiece
    {
        if (!isset($this->pieces[$pieceNumber])) {
            throw new InvalidArgumentException('Piece with number ' . $pieceNumber . ' not found.');
        }

        return $this->pieces[$pieceNumber];
    }

    public function getPiecesCount(): int
    {
        return $this->piecesCount;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getMatchingMap(): array
    {
        return $this->matchingMap;
    }

    /**
     * @return array<string, float>
     */
    public function getMatchingProbabilities(string $key): array
    {
        return $this->matchingMap[$key] ?? [];
    }

    public function getMatchingProbability(string $key1, string $key2): float
    {
        return $this->matchingMap[$key1][$key2] ?? 0;
    }

    /**
     * @param array<string, array<string, float>> $matchingMap
     */
    public function setMatchingMap(array $matchingMap): ByWulfSolverContext
    {
        $this->matchingMap = $matchingMap;

        return $this;
    }

    public function unsetMatchingMapKey(string $key): ByWulfSolverContext
    {
        unset($this->matchingMap[$key]);

        return $this;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getOriginalMatchingMap(): array
    {
        return $this->originalMatchingMap;
    }

    public function getOriginalMatchingProbability(string $key1, string $key2): float
    {
        return $this->originalMatchingMap[$key1][$key2] ?? 0.0;
    }

    public function isRemovingAllowed(): bool
    {
        return $this->removingAllowed;
    }

    public function setRemovingAllowed(bool $removingAllowed): ByWulfSolverContext
    {
        $this->removingAllowed = $removingAllowed;

        return $this;
    }

    public function getStepProgression(): ?Closure
    {
        return $this->stepProgression;
    }

    public function getSolutionReporter(): ?Closure
    {
        return $this->solutionReporter;
    }
}
