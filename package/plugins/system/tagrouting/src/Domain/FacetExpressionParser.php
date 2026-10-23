<?php

declare(strict_types=1);

namespace SuperSoft\Plugin\System\TagRouting\Domain;

use SuperSoft\Plugin\System\TagRouting\Configuration\FacetLimits;

final class FacetExpressionParser
{
    public function __construct(private readonly ?FacetLimits $limits = null)
    {
    }

    public function parse(string $input): ?ParsedFacetExpression
    {
        // Joomla's whole-path urldecode() turns a literal plus into a space.
        $input = str_replace(' ', '+', trim($input));

        if ($input === '' || strlen($input) > ($this->limits?->maxBytes ?? 2048) || strpbrk($input, '()') !== false) {
            return null;
        }

        $groups = [];
        $totalOptions = 0;

        foreach (explode('+', $input) as $rawGroup) {
            if ($rawGroup === '' || count($groups) >= ($this->limits?->maxGroups ?? 16)) {
                return null;
            }

            $paths = [];

            foreach (explode(',', $rawGroup) as $rawPath) {
                if ($rawPath === '' || count($paths) >= ($this->limits?->maxOrOptions ?? 32)
                    || ++$totalOptions > ($this->limits?->maxOptions ?? 512)) {
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
            }

            $groups[] = $paths;
        }

        // Raw limits above apply before deduplication. Never sort hierarchy segments.
        $canonical = [];
        foreach ($groups as $paths) {
            $options = [];
            foreach ($paths as $aliases) {
                $options[implode('/', $aliases)] = $aliases;
            }
            ksort($options, SORT_STRING);
            $canonical[implode(',', array_keys($options))] = array_values($options);
        }
        ksort($canonical, SORT_STRING);

        return new ParsedFacetExpression(implode('+', array_keys($canonical)), array_values($canonical));
    }
}
