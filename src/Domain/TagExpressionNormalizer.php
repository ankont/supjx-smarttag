<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final class TagExpressionNormalizer
{
    public function normalize(TagExpression $expression): TagExpression
    {
        $groups = [];

        foreach ($expression->groups as $group) {
            $tags = array_values(array_filter(
                $group->tags,
                static function (TagNode $candidate) use ($group): bool {
                    // A OR descendant(A) is simply A.
                    foreach ($group->tags as $other) {
                        if ($candidate->id !== $other->id && $other->contains($candidate)) {
                            return false;
                        }
                    }

                    return true;
                },
            ));
            $groups[] = new TagGroup($tags);
        }

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

        return new TagExpression($result);
    }

    private function isImpliedBy(TagGroup $candidate, TagGroup $other): bool
    {
        foreach ($other->tags as $otherTag) {
            $covered = false;

            foreach ($candidate->tags as $candidateTag) {
                if ($candidateTag->contains($otherTag)) {
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
