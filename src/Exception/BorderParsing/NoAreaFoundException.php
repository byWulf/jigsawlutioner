<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Exception\BorderParsing;

use Bywulf\Jigsawlutioner\Exception\BorderParsingException;

class NoAreaFoundException extends BorderParsingException
{
    public function __construct()
    {
        parent::__construct('No area found');
    }
}
