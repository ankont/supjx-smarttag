<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

final class FacetExpressionParser
{
    public function parse(string $input): ?ParsedFacetExpression
    {
        // Joomla's whole-path urldecode() turns a literal plus into a space.
        $input = str_replace(' ', '+', trim($input));

        if ($input === '' || strlen($input) > 2048 || strpbrk($input, '()') !== false) {
            return null;
        }

        $groups = [];
        $canonicalGroups = [];

        foreach (explode('+', $input) as $rawGroup) {
            if ($rawGroup === '' || count($groups) >= 16) {
                return null;
            }

            $paths = [];
            $canonicalPaths = [];

            foreach (explode(',', $rawGroup) as $rawPath) {
                if ($rawPath === '' || count($paths) >= 32) {
                    return null;
                }

                $aliases = explode('/', $rawPath);

                if (in_array('', $aliases, true)) {
                    return null;
                }

                foreach ($aliases as $alias) {
                    if (strlen($alias) > 255) {
                        return null;
                    }
                }

                $paths[] = $aliases;
                $canonicalPaths[] = implode('/', $aliases);
            }

            $groups[] = $paths;
            $canonicalGroups[] = implode(',', $canonicalPaths);
        }

        return new ParsedFacetExpression(implode('+', $canonicalGroups), $groups);
    }
}
