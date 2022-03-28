<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Tests\Validator\Group;

use Bywulf\Jigsawlutioner\Dto\Group;
use Bywulf\Jigsawlutioner\Dto\Piece;
use Bywulf\Jigsawlutioner\Dto\Placement;
use Bywulf\Jigsawlutioner\Exception\GroupInvalidException;
use Bywulf\Jigsawlutioner\Validator\Group\UniquePlacement;
use Bywulf\Jigsawlutioner\Validator\Group\UniquePlacementValidator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniquePlacementValidatorTest extends TestCase
{
    private UniquePlacementValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UniquePlacementValidator();
    }

    public function testValidateWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Group(), new NotBlank());
    }

    public function testValidateWrongValue(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new stdClass(), new UniquePlacement());
    }

    public function testValidateSuccess(): void
    {
        $value = new Group();
        $value->addPlacement(new Placement(1, 2, new Piece(1, [], [], 1, 1), 0));
        $value->addPlacement(new Placement(1, 3, new Piece(2, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(2, 2, new Piece(3, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 3, new Piece(4, [], [], 1, 1), 3));

        $this->validator->validate($value, new UniquePlacement());

        $this->assertNull(null);
    }

    public function testValidateSuccessWithMax(): void
    {
        $value = new Group();
        $value->addPlacement(new Placement(1, 2, new Piece(1, [], [], 1, 1), 0));
        $value->addPlacement(new Placement(1, 3, new Piece(2, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(1, 3, new Piece(5, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(2, 2, new Piece(3, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 2, new Piece(6, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 3, new Piece(4, [], [], 1, 1), 3));

        $this->validator->validate($value, new UniquePlacement(['maxAllowedDoubles' => 2]));

        $this->assertNull(null);
    }

    public function testValidateFailure(): void
    {
        $value = new Group();
        $value->addPlacement(new Placement(1, 2, new Piece(1, [], [], 1, 1), 0));
        $value->addPlacement(new Placement(1, 3, new Piece(2, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(1, 3, new Piece(5, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(2, 2, new Piece(3, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 3, new Piece(4, [], [], 1, 1), 3));

        $this->expectException(GroupInvalidException::class);

        $this->validator->validate($value, new UniquePlacement());
    }

    public function testValidateFailureWithMax(): void
    {
        $value = new Group();
        $value->addPlacement(new Placement(1, 2, new Piece(1, [], [], 1, 1), 0));
        $value->addPlacement(new Placement(1, 3, new Piece(2, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(1, 3, new Piece(5, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(2, 2, new Piece(3, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 2, new Piece(6, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 3, new Piece(4, [], [], 1, 1), 3));
        $value->addPlacement(new Placement(2, 3, new Piece(7, [], [], 1, 1), 3));

        $this->expectException(GroupInvalidException::class);

        $this->validator->validate($value, new UniquePlacement(['maxAllowedDoubles' => 2]));
    }

    public function testValidateFailureWithTripplets(): void
    {
        $value = new Group();
        $value->addPlacement(new Placement(1, 2, new Piece(1, [], [], 1, 1), 0));
        $value->addPlacement(new Placement(1, 3, new Piece(2, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(1, 3, new Piece(5, [], [], 1, 1), 1));
        $value->addPlacement(new Placement(2, 2, new Piece(3, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 2, new Piece(6, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 2, new Piece(7, [], [], 1, 1), 2));
        $value->addPlacement(new Placement(2, 3, new Piece(4, [], [], 1, 1), 3));

        $this->expectException(GroupInvalidException::class);

        $this->validator->validate($value, new UniquePlacement(['maxAllowedDoubles' => 2]));
    }
}
