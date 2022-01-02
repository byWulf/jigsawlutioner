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

    /**
     * @param Group[] $groups
     */
    public function setGroups(array $groups): self
    {
        $this->groups = $groups;

        return $this;
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

    public function getGroupByPiece(Piece $piece): ?Group
    {
        foreach ($this->groups as $group) {
            if ($group->getPlacementByPiece($piece) !== null) {
                return $group;
            }
        }

        return null;
    }

    public function getPieceCount(): int
    {
        return array_sum(array_map(fn(Group $group): int => count($group->getPlacements()), $this->getGroups()));
    }
}
