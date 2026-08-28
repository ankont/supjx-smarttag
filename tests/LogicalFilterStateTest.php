<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Tests;

use PHPUnit\Framework\TestCase;
use SuperSoft\Plugin\System\TagRouting\Domain\SerializedExpression;
use SuperSoft\Plugin\System\TagRouting\Menu\LogicalFilterState;

final class LogicalFilterStateTest extends TestCase
{
    public function testTagAndTypeOrderingDoesNotChangeLogicalState(): void
    {
        $states = new LogicalFilterState(new SerializedExpression());
        $left = $states->from(['filter_tag' => [42, 17], 'types' => [2, 1]]);
        $right = $states->from(['filter_tag' => [17, 42], 'types' => [1, 2]]);

        self::assertSame($left, $right);
    }

    public function testMatchAllBecomesAndGroups(): void
    {
        $state = (new LogicalFilterState(new SerializedExpression()))->from([
            'tags' => [42, 17],
            'match_all' => 1,
        ]);

        self::assertSame([[17], [42]], $state['groups']);
    }
}
