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
use Bywulf\Jigsawlutioner\Validator\Group\RectangleGroup;
use Bywulf\Jigsawlutioner\Validator\Group\RectangleGroupValidator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @covers \Bywulf\Jigsawlutioner\Validator\Group\RectangleGroupValidator
 */
class RectangleGroupValidatorTest extends TestCase
{
    private RectangleGroupValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RectangleGroupValidator();
    }

    public function testValidateWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Group(), new NotBlank());
    }

    public function testValidateWrongValue(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new stdClass(), new RectangleGroup());
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

        $this->validator->validate($value, new RectangleGroup());
    }

    public function validateProvider(): iterable
    {
        $placements = [$this->mockPlacement(0, 0, 's', 's', 's', 's', 0)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(1, 0, 's', 's', 's', 's', 0)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(1, 5, 's', 's', 's', 's', 0)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(0, 0, 's', 's', 's', 's', 1)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(0, 0, 's', 's', 's', 's', 2)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(1, 3, 's', 's', 's', 's', 2)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(0, 0, 'i', 's', 's', 's', 0)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(0, 0, 'i', 's', 's', 's', 1)];
        yield [$placements, false];

        $placements = [$this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 2)];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 's', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 's', 'i', 'o', 's', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(1, 0, 's', 's', 'i', 'o', 1),
            $this->mockPlacement(0, 0, 'o', 's', 's', 'i', 1),
            $this->mockPlacement(0, 1, 'i', 'o', 's', 's', 1),
            $this->mockPlacement(1, 1, 's', 'i', 'o', 's', 1),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'o', 's', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'o', 's', 0),
            $this->mockPlacement(0, -1, 'o', 's', 'o', 'o', 0),
        ];
        yield [$placements, false];

        $placements = [
            $this->mockPlacement(0, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'o', 's', 0),
            $this->mockPlacement(0, -1, 'o', 'o', 'o', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 's', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'o', 's', 0),
            $this->mockPlacement(0, -1, 'o', 's', 'o', 'o', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 's', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'o', 's', 0),
            $this->mockPlacement(1, -1, 'o', 'i', 'o', 's', 0),
        ];
        yield [$placements, true];

        $placements = [
            $this->mockPlacement(0, 0, 's', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(0, 1, 'o', 's', 's', 'i', 0),
            $this->mockPlacement(1, 1, 'i', 'o', 's', 's', 0),
            $this->mockPlacement(1, 0, 'i', 'i', 'o', 's', 0),
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
