<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Dto\Point;
use Bywulf\Jigsawlutioner\Dto\Side;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\SideClassifier\DirectionClassifier;
use Bywulf\Jigsawlutioner\Validator\Group\PossibleSideMatching;
use Bywulf\Jigsawlutioner\Validator\Group\PossibleSideMatchingValidator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PossibleSideMatchingValidatorTest extends TestCase
{
    private PossibleSideMatchingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PossibleSideMatchingValidator();
    }

    public function testValidateWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Group(), new NotBlank());
    }

    public function testValidateWrongValue(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new stdClass(), new PossibleSideMatching());
    }

    /**
     * @dataProvider validateProvider
     */
    public function testValidate(array $placements, bool $expectException): void
    {
        $value = new Group();
        foreach ($placements as $placement) {
            $value->addPlacement($placement);
        }

        if ($expectException) {
            $this->expectException(GroupInvalidException::class);
        } else {
            $this->assertNull(null);
        }

        $this->validator->validate($value, new PossibleSideMatching());
    }

    public function validateProvider(): iterable
    {
        $placements = [$this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(0, 0, 's', 's', 'i', 'o', 0)];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(2, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(1, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 1),
            $this->mockPlacement(1, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 1),
            $this->mockPlacement(1, 0, 'i', 'i', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 2, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 1),
            $this->mockPlacement(0, 1, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 1),
            $this->mockPlacement(0, 1, 'o', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(1, 0, 'i', 's', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'i', 'o', 's', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 's', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 0, 'i', 'o', 'o', 'o', 0),
            $this->mockPlacement(0, 1, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'o', 'o', 0),
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'i', 'o', 'i', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 'o', 'i', 'o', 0),
        ];
        yield [$placements, false];
    }

    private function mockPlacement(int $x, int $y, string $top, string $left, string $bottom, string $right, int $topSide): Placement
    {
        return new Placement(
            $x,
            $y,
            new Piece(1, [], [
                (new Side([], new Point(0, 0), new Point(0, 0)))->addClassifier(new DirectionClassifier($this->mapDirection($top))),
                (new Side([], new Point(0, 0), new Point(0, 0)))->addClassifier(new DirectionClassifier($this->mapDirection($left))),
                (new Side([], new Point(0, 0), new Point(0, 0)))->addClassifier(new DirectionClassifier($this->mapDirection($bottom))),
                (new Side([], new Point(0, 0), new Point(0, 0)))->addClassifier(new DirectionClassifier($this->mapDirection($right))),
            ], 0, 0),
            $topSide
        );
    }

    private function mapDirection(string $shortDirection): int
    {
        return match ($shortDirection) {
            'i' => DirectionClassifier::NOP_INSIDE,
            'o' => DirectionClassifier::NOP_OUTSIDE,
            's' => DirectionClassifier::NOP_STRAIGHT,
        };
    }
}
