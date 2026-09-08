<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final readonly class TagNode
{
    public function __construct(
        public int $id,
        public string $alias,
        public int $parentId,
        public int $lft,
        public int $rgt,
        public int $rootId,
        public string $title = '',
    ) {
    }

    public function contains(self $other): bool
    {
        return $this->lft <= $other->lft && $this->rgt >= $other->rgt;
    }
}
