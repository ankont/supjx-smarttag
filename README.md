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
/category/path/!/tag1/tag2
```

Examples:

```text
/selections/seasonal/!/christmas
/lessons/!/a2/math
/lessons/!/a2+a3/math
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

## Boolean grammar

`/` after the delimiter means AND. `+` inside one segment means OR:

```text
/lessons/!/a2/math                 A2 AND Math
/lessons/!/a2+a3/math              (A2 OR A3) AND Math
/lessons/!/a2+a3/math+physics      (A2 OR A3) AND (Math OR Physics)
```

The resolver restores a literal `+` after Joomla's whole-path `urldecode()` behavior; it is not treated as query-string whitespace.

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
/seasonal/!/christmas/lower
```

The configured root may be at any depth in the global tag tree. A category without assigned tags remains an ordinary Joomla category. Configured root tags are boundaries; their descendants are the routable facets.

Resolution includes only published tags visible to the current user's access levels and compatible with the current language. Parsed metadata retains the configured root ID for every resolved tag, allowing templates to keep Period/Grade-style UI groups separate.

## Hierarchical resolution

Aliases after the delimiter use the preceding resolved tag branch as context. Thus:

```text
/category/!/bible/parables
```

selects `Parables` specifically below the already resolved `Bible`, even if another branch also contains a `parables` alias. If no descendant matches, resolution returns to the union of configured roots so a new facet can begin. An ambiguous alias is never guessed.

## Public path and effective filter

Visible hierarchy is preserved while redundant database constraints are normalized:

```text
Public:    /category/!/bible/parables
Effective: Parables
```

Within an OR group, `Ancestor OR Descendant` becomes `Ancestor`. Across AND groups, an ancestor group implied by a more specific group is removed. SQL uses `IN (...)` for OR and one correlated `EXISTS` per AND group.

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

Templates construct a resolved `TagExpression` and let Joomla build the URL:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRoute;

$url = FacetedRoute::get($categoryId, $expression, $itemId);
```

`FacetedRoute` never concatenates aliases. The component router always emits the canonical `!/` delimiter. A template override can read structured request metadata with `FacetedRequest::current()`; the filtering layer separately revalidates all IDs against live taxonomy, publication, access, and language state.

## Internal request state

Parsed state is stored under the namespaced `__tagrouting` variable as versioned JSON containing:

- category ID;
- canonical public expression segments;
- normalized effective ID groups;
- each resolved tag's ID, alias, and configured root ID.

The article model does not trust client-provided state: every tag ID is revalidated before the core query is extended.

## Installation and development

Run `build/build.ps1`, install `build/plg_system_tagrouting-v1.5.0.zip`, and enable **System - Clean Tag Routing**. The tested runtime target is Joomla 5/6 with PHP 8.3 or later.

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
- Multiple roots resolve independent AND facets.
- Nested `!/bible/parables` remains public but filters only by `Parables`.
- Duplicate aliases in other branches do not destabilize contextual paths.
- An ambiguous first tag alias fails safely.
- One and two OR groups preserve literal `+` semantics.
- Categories without roots use core routing only.
- Real categories/articles before the delimiter use core routing.
- Exact multi-tag menu states remove redundant parameters.
- Unpublished, inaccessible, and wrong-language tags do not resolve.
- Pagination, layouts, ordinary routes, frontend, backend, and CLI remain functional.

## Limitations

- Boolean grammar is limited to OR within a segment and AND between segments; there is no NOT or parentheses.
- Taxonomy roots are configuration boundaries and are not emitted as facet choices.
- Site-specific custom menu filter keys require extending `LogicalFilterState`.
- End-to-end router and SQL tests require a Joomla site/database. The bundled unit suite covers delimiters, canonical output, grammar, ambiguity, hierarchy, normalization, serialization, and logical menu state.
