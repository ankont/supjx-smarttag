<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tag;

final readonly class TagHierarchyNode
{
    public function __construct(
        public int $id,
        public string $alias,
        public int $parentId,
        public int $lft,
        public int $rgt,
        public string $language,
        public string $title = '',
    ) {
    }
}
