# System - Clean Tag Routing

A reusable Joomla 5/6 system plugin that provides menu-aware tag URL cleanup and hierarchical faceted tag routes below `com_content` categories. It modifies no Joomla core files and renders no HTML.

## Integration approach

The plugin follows Joomla's supported router-extension pattern:

- `onAfterExtensionBoot` replaces only the `com_content` `RouterFactory` namespace. The plugin router extends the core content router and retains its view configuration and rules.
- Core `StandardRules` and `NomenuRules` resolve the real Joomla route first.
- `FacetedParseRule` activates only when the first remaining segment is a supported facet delimiter.
- `FacetedBuildRule` appends the canonical `!/` marker and filter expression after the real category path.
- `CanonicalMenuRules` and a site build rule select exact menu-defined filter states and remove redundant public variables. The generic rule also covers `com_tags` without replacing its router.
- A fallback MVC factory changes only the site `ArticlesModel`. Every other MVC class resolves to Joomla core. The subclass calls the core query and adds the minimum grouped-tag constraints.

This avoids parsing `REQUEST_URI`, replacing `com_content`, or duplicating category/article rendering. The design follows Joomla's official [System Plugin Router Rules](https://manual.joomla.org/docs/next/building-extensions/plugins/plugin-examples/system-plugin-router-rules/) pattern.

## Canonical delimiter

The canonical syntax explicitly separates the real Joomla route from virtual tag filtering:

```text
/category/path/!/tag1,tag2+tag3
```

Examples:

```text
/selections/seasonal/!/christmas
/lessons/!/secondary/gymnasium
/lessons/!/a2,a3+math
```

Everything before `!/` is resolved exclusively by Joomla. Everything after it is parsed exclusively as the faceted expression. A bare path such as `/category/christmas` is never interpreted as a tag filter.

Two explicit input aliases are accepted:

```text
/selections/seasonal/!filter/christmas
/selections/seasonal/!tag/christmas
```

They resolve to the same filter state, but are non-canonical. Joomla's SEF canonical flow redirects both to:

```text
/selections/seasonal/!/christmas
```

Bare `/filter/` and `/tag/` delimiters are deliberately unsupported.

## Facet grammar

The v2 grammar uses three deliberately distinct separators:

```text
/   hierarchy inside one tag path
,   OR between alternative tag paths
+   AND between filter groups
```

OR alternatives form a group first; `+` then requires all groups. Parentheses are intentionally unsupported, keeping parsing, canonicalization and SQL filtering predictable:

```text
/lessons/!/secondary/gymnasium                 Secondary/Gymnasium
/lessons/!/a2,a3+math                          (A2 OR A3) AND Math
/lessons/!/a2,a3+math,physics                  (A2 OR A3) AND (Math OR Physics)
/lessons/!/preschool,primary,secondary/gymnasium
                                                Preschool OR Primary OR Secondary/Gymnasium
```

The formal grammar is:

```text
expression := group ("+" group)*
group      := path ("," path)*
path       := alias ("/" alias)*
```

The resolver restores a literal `+` after Joomla's whole-path `urldecode()` behavior; it is not treated as query-string whitespace. This is a breaking change from v1.5: `/` no longer means AND and `+` no longer means OR.

## Taxonomy configuration

Assign one or more native Joomla tags to a content category. Each assigned tag becomes a taxonomy root and enables virtual routing only inside its descendant subtree.

For a category tagged with roots `Period` and `Grade`:

```text
Period                 Grade
|-- Christmas          |-- Preschool
`-- Easter             `-- Lower
```

valid routes include:

```text
/seasonal/!/christmas
/seasonal/!/christmas+lower
```

The configured root may be at any depth in the global tag tree. A category without assigned tags remains an ordinary Joomla category. Configured root tags are boundaries; their descendants are the routable facets.

Resolution includes only published tags visible to the current user's access levels and compatible with the current language. Parsed metadata retains the configured root ID for every resolved tag, allowing templates to keep Period/Grade-style UI groups separate.

## Hierarchical resolution

Aliases separated by `/` form one explicit hierarchical tag path. Thus:

```text
/category/!/bible/parables
```

selects `Parables` specifically below `Bible`, even if another branch also contains a `parables` alias. A path never silently jumps to another root: start a new AND group with `+` or an OR alternative with `,`. An ambiguous first alias is never guessed.

Explicit parent paths also disambiguate identical child aliases:

```text
/category/!/primary/gymnasium,secondary/gymnasium
```

## Public path and effective filter

Visible hierarchy is preserved while redundant database constraints are normalized:

```text
Public:    /category/!/bible/parables
Effective: Parables
```

The visible parent path disambiguates the leaf but does not add a second SQL constraint. Within an OR group, `Ancestor OR Descendant` becomes `Ancestor`. Across AND groups, an ancestor group implied by a more specific group is removed. SQL uses `IN (...)` for OR and one correlated `EXISTS` per AND group.

## Separation from real Joomla routes

The delimiter removes competition between real routes and tag aliases:

```text
/seasonal/christmas      Real Joomla route only
/seasonal/!/christmas    Explicit tag facet
```

Joomla resolves every segment before `!`; the plugin handles only segments after it. Categories may therefore expose real subcategories and taxonomy facets independently.

## Menu-aware cleanup

When a Joomla menu item already contains the requested tag/type/language state, generated links use that menu item's clean route and omit duplicate query variables. Tag ordering does not affect comparison. `match_all` or `tag_list_all` creates singleton AND groups; otherwise a tag list is treated as one OR group.

Recognized Joomla filter keys are `filter_tag`, `tags`, `tag_ids`, `com_tags` `id`, `types`, and `tag_list_language_filter`.

## Building links

Templates construct resolved paths and let Joomla build the URL. The domain objects preserve the distinction between hierarchy (`/`) and Boolean grouping:

```php
use SuperSoft\Plugin\System\TagRouting\Domain\FacetExpression;
use SuperSoft\Plugin\System\TagRouting\Domain\FacetGroup;
use SuperSoft\Plugin\System\TagRouting\Domain\TagPath;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRoute;

$expression = new FacetExpression([
    new FacetGroup([
        new TagPath([$preschool]),
        new TagPath([$primary, $primaryGymnasium]),
        new TagPath([$secondary, $secondaryGymnasium]),
    ]),
    new FacetGroup([new TagPath([$math])]),
]);

$url = FacetedRoute::get($categoryId, $expression, $itemId);
// /category/!/preschool,primary/gymnasium,secondary/gymnasium+math
```

`FacetedRoute` never requires templates to concatenate aliases. It also continues to accept a flat `TagExpression` when no explicit hierarchy is needed. The component router always emits the canonical `!/` delimiter. A template override can read structured request metadata with `FacetedRequest::current()`; the filtering layer separately revalidates all IDs against live taxonomy, publication, access, and language state.

## Internal request state

Parsed state is stored under the namespaced `__tagrouting` variable as versioned JSON containing:

- category ID;
- canonical public expression using `/`, `,`, and `+`;
- normalized effective ID groups;
- each resolved tag's ID, alias, and configured root ID.

The article model does not trust client-provided state: every tag ID is revalidated before the core query is extended.

## Installation and development

Run `build/build.ps1`, install `build/plg_system_tagrouting-v2.0.0.zip`, and enable **System - Clean Tag Routing**. The tested runtime target is Joomla 5/6 with PHP 8.3 or later.

For PHPUnit:

```text
composer install
composer test
```

Without Composer, run:

```text
php tests/run.php
```

## Test checklist

- `/category/!/christmas`, `/category/!filter/christmas`, and `/category/!tag/christmas` resolve the same state.
- Generated and redirected canonical URLs use `/category/!/christmas`.
- Bare `/category/christmas`, `/category/filter/christmas`, and `/category/tag/christmas` never activate facets.
- Simple and non-top-level taxonomy roots resolve.
- `,` resolves OR alternatives and `+` resolves independent AND groups.
- Nested `!/bible/parables` remains public but filters only by `Parables`.
- Duplicate aliases in other branches do not destabilize contextual paths.
- Explicit parent paths distinguish the same child alias below different branches.
- An ambiguous first tag alias fails safely.
- Joomla-decoded spaces restore literal `+` AND semantics.
- Parenthesized and malformed expressions fail safely.
- Categories without roots use core routing only.
- Real categories/articles before the delimiter use core routing.
- Exact multi-tag menu states remove redundant parameters.
- Unpublished, inaccessible, and wrong-language tags do not resolve.
- Pagination, layouts, ordinary routes, frontend, backend, and CLI remain functional.

## Limitations

- The grammar supports positive AND-of-OR filters only; there is no NOT or parentheses.
- Taxonomy roots are configuration boundaries and are not emitted as facet choices.
- Site-specific custom menu filter keys require extending `LogicalFilterState`.
- End-to-end router and SQL tests require a Joomla site/database. The bundled unit suite covers delimiters, canonical output, grammar, ambiguity, hierarchy, normalization, serialization, and logical menu state.
