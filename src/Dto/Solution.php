<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Dto;

class Solution
{
    /**
     * @param Group[] $groups
     */
    public function __construct(
        private array $groups = []
    ) {
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function addGroup(Group $group): Solution
    {
        $this->groups[] = $group;

        return $this;
    }

    public function removeGroup(Group $group): Solution
    {
        $key = array_search($group, $this->groups, true);
        if ($key !== false) {
            unset($this->groups[$key]);
        }

        return $this;
    }
}
