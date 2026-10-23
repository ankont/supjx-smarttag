<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final class TagExpressionNormalizer
{
    public function normalize(TagExpression $expression): TagExpression
    {
        // SQL tests exact IDs, not descendant closure. Do not infer membership
        // from nested-set ancestry: an article may carry only the child tag.
        $groups = $expression->groups;

        $result = [];

        foreach ($groups as $index => $candidate) {
            $redundant = false;

            foreach ($groups as $otherIndex => $other) {
                if ($index === $otherIndex) {
                    continue;
                }

                // Candidate is redundant when every alternative in another AND
                // group implies at least one of candidate's alternatives.
                if ($this->isImpliedBy($candidate, $other)) {
                    if ($this->isImpliedBy($other, $candidate) && $otherIndex > $index) {
                        continue;
                    }

                    $redundant = true;
                    break;
                }
            }

            if (!$redundant) {
                $result[] = $candidate;
            }
        }

        usort($result, static fn (TagGroup $a, TagGroup $b): int => strcmp(implode(',', $a->ids()), implode(',', $b->ids())));

        return new TagExpression($result);
    }

    private function isImpliedBy(TagGroup $candidate, TagGroup $other): bool
    {
        foreach ($other->tags as $otherTag) {
            $covered = false;

            foreach ($candidate->tags as $candidateTag) {
                if ($candidateTag->id === $otherTag->id) {
                    $covered = true;
                    break;
                }
            }

            if (!$covered) {
                return false;
            }
        }

        return true;
    }
}
