<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Solution
{
    /**
     * @param Group[] $groups
     */
    public function __construct(
        private array $groups
    ) {
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
