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

    public function outputSolution(): void
    {
        foreach ($this->groups as $index => $group) {
            echo 'Group #' . $index . ':' . PHP_EOL;
            foreach ($group->getPlacements() as $placement) {
                echo "\t" . 'x: ' . $placement->getX() . ', y: ' . $placement->getY() . ', top side: ' . $placement->getTopSideIndex() . ', pieceIndex: ' . $placement->getPiece()->getIndex() . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }
}
