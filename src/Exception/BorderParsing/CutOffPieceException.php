<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Exception\BorderParsing;

use Bywulf\Jigsawlutioner\Exception\BorderParsingException;

class CutOffPieceException extends BorderParsingException
{
    public function __construct()
    {
        parent::__construct('Piece is cut off');
    }
}
