<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Service\SideFinder;

use Bywulf\Jigsawlutioner\Service\PuzzleSolver\ByWulfSolver;
use Bywulf\Jigsawlutioner\Service\SideMatcher\WeightedMatcher;
use Bywulf\Jigsawlutioner\Tests\PieceLoaderTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ByWulfSolverTest extends TestCase
{
    use ProphecyTrait;
    use PieceLoaderTrait;

    private ByWulfSolver $solver;

    protected function setUp(): void
    {
        $this->solver = new ByWulfSolver(
            new WeightedMatcher()
        );
    }

    public function testGetSidesSimple(): void
    {
        $this->solver->findSolution($this->getPieces());
    }
}
