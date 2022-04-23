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
use Bywulf\Jigsawlutioner\Validator\Group\RealisticSide;
use Bywulf\Jigsawlutioner\Validator\Group\RealisticSideValidator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @covers \Bywulf\Jigsawlutioner\Validator\Group\RealisticSideValidator
 */
class RealisticSideValidatorTest extends TestCase
{
    private RealisticSideValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RealisticSideValidator();
    }

    public function testValidateWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Group(), new NotBlank());
    }

    public function testValidateWrongValue(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new stdClass(), new RealisticSide(['piecesCount' => 500]));
    }

    /**
     * @dataProvider validateProvider
     */
    public function testValidate(int $piecesCount, array $placements, bool $expectException): void
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

        $this->validator->validate($value, new RealisticSide(['piecesCount' => $piecesCount]));
    }

    public function validateProvider(): iterable
    {
        $placements = [$this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 0)];
        yield [500, $placements, false];

        $placements = [$this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 1)];
        yield [500, $placements, false];

        $placements = [$this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 2)];
        yield [500, $placements, false];

        $placements = [$this->mockPlacement(0, 0, 'i', 'o', 'i', 'o', 3)];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(-10, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(16, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(-10, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(17, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(-10, -20, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(-10, 6, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(-10, -20, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(-10, 7, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(-20, -20, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(20, 20, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(-5, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(5, 0, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(-9, 0, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(5, 0, 'i', 'o', 'i', 's', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(-9, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(7, 0, 'i', 'o', 'i', 's', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(-9, 0, 'i', 's', 'i', 'o', 0),
            $this->mockPlacement(6, 0, 'i', 'o', 'i', 's', 0),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(0, -5, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 5, 'i', 'o', 'i', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(0, -9, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 5, 'i', 'o', 's', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(0, -9, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 7, 'i', 'o', 's', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(0, -9, 'i', 's', 'i', 'o', 1),
            $this->mockPlacement(0, 7, 'i', 'o', 's', 'o', 0),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(0, -9, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 7, 'i', 'o', 'i', 's', 1),
        ];
        yield [500, $placements, false];

        $placements = [
            $this->mockPlacement(0, -9, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 6, 'i', 'o', 's', 'o', 0),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(0, -9, 'i', 's', 'i', 'o', 1),
            $this->mockPlacement(0, 6, 'i', 'o', 's', 'o', 0),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(0, -9, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 6, 'i', 'o', 'i', 's', 1),
        ];
        yield [500, $placements, true];

        $placements = [
            $this->mockPlacement(0, -9, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, -9, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 6, 'i', 'o', 's', 'o', 0),
        ];
        yield 'only the last pieces are taken into account #1' => [500, $placements, false];

        $placements = [
            $this->mockPlacement(0, -9, 'i', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, -9, 's', 'o', 'i', 'o', 0),
            $this->mockPlacement(0, 6, 'i', 'o', 's', 'o', 0),
        ];
        yield 'only the last pieces are taken into account #2' => [500, $placements, true];
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
