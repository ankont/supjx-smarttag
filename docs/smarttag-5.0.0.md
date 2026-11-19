# SmartTag 5.0.0

Package **5.0.0**, routing **5.0.0**, component **1.5.0**, Select **1.7.2**.
Versions remain independent. Nothing is enabled automatically on upgrade.

## Category filtering

Routing plugin > Available category filters:

- Category tags only (default): current behavior.
- Category and ancestor category tags: additive union across all ancestors.
  There is no exclusion/override mechanism in a child category.
- All available tags: category assignments no longer restrict the taxonomy.

Include descendant tags in category filters defaults to No. When enabled,
each selected tag matches itself and available descendants. OR remains within
groups and AND remains between groups. Root scope expansion and result matching
are separate settings. Existing publication, ACL and language scope checks apply.

Template scope source is unchanged:

```php
$scope = CategoryTaxonomyResolver::forApplication($app, $db)
    ->resolve($categoryId, $viewLevels, $language);
```

The current Catichisi category helper already uses this resolver. Other consumers
must not independently read only the current category's tag assignments.
Showing all configured filters versus only relevant choices remains a template decision.

## Selection-preserving canonicalization

Canonical paths sort AND groups and OR options and remove duplicate tags/groups.
They never reorder hierarchy segments. Distinct selections are retained even
when they produce equivalent results. For example `a+a,b` stays `a+a,b`, rather
than redirecting to `a`. Selected ancestors and descendants remain selected in
both matching modes. SQL-only exact Boolean optimizations remain separate.

This is a deliberate canonical URL contract change from 4.x. Previously generated
simplified URLs still work; they cannot recover selections that were already lost.

## Additional standalone tag filters

SmartTag Pages > Options:

- Enable additional tag filters: No by default.
- Available additional filters: selected roots/descendants (empty means none),
  or explicitly all available tags.
- Filter taxonomy roots: multiple selection.
- Include descendants in additional filter matching: No by default. Independent
  of native com_tags Include Children for the base tag.

Requires hierarchical routing enabled, a nonempty menu route, and Advanced >
Use menu route as canonical URL = Yes. Inconsistent settings cannot generate
filtered links and incoming filtered paths return 404; enable canonical first.
Existing root-marker binding need not change.

```text
/pages/nav/tags/series/example/!/age-group/primary+virgin-mary.html
```

The complete real SmartTag menu route is matched first (longest namespace wins).
The base tag hierarchy ends at the extra marker. The base native tag predicate
is ANDed with additional groups, for every tagged content type. `content-types`,
`content-category`, menu type restrictions and visibility guards also intersect.

Root-marker standalone double-marker paths are unsupported. Category filtering
such as `/pages/nav/!/primary` is left to the content router. Marker aliases are
accepted according to existing settings and rebuild to the canonical marker.
Joomla's SEF tainted-route redirect handles valid noncanonical paths; the target
is canonical immediately. Existing FacetLimits apply to raw input before lookup
and deduplication. No separate full expression parser has been added.

Public PHP APIs:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute;
use SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRequest;

$scope = TagFilterRoute::available($itemId, $language); // roots + nodes
$current = TagFilterRequest::current(); // validated tag/item/expression/selected/groups
$url = TagFilterRoute::get($tagId, $itemId, $expression, [
    'cat-view' => 'details',
    'content-types' => 'com_content.article',
]);
$clear = TagFilterRoute::get($tagId, $itemId, '', $parameters);
```

`$expression` may be a string or FacetExpression. Invalid/disabled builders return
an empty string. Builders start fresh and discard offsets; clear returns the base
tag page, retaining only explicitly supplied supported public parameters.
`selected` contains user tag IDs; `groups` contains effective matching IDs.
Never use expanded matching IDs as active-control selections.

The private `__tagrouting_extra` is stripped at the incoming-query boundary.
Only the parser records validated per-request state. Internal pagination may
carry a minimal serialized tag/menu/expression; builders re-resolve its scope
and consume it into the path. It must never appear in public links. State is
reset on initialization and cannot be inherited by an unrelated tag/category.

Pagination preserves additional tag filters and explicit query restrictions;
filter changes reset offsets. View switchers may preserve the current path and
supported public query parameters, stripping every `__tagrouting*` key. Ordinary
new tag/category links start fresh unless retaining a restriction is intentional.

## Article category groups

SmartTag Pages > Options > Group articles by category: No by default.
This affects com_content only:

- Excludes category items in SQL before results/counts/pagination/feeds.
- Groups articles by their live `#__content.catid`, not category tag assignments.
- Orders groups contiguously by category title/ID, then retains native item
  ordering within a group and a stable content-item tie-breaker.
- Other content types stay separate by type; one native pagination remains.

`getItems()` remains flat for native com_tags compatibility. Each result receives
`supjx_group_key` and `supjx_group_title`; article results also receive
`supjx_category_id` and `supjx_category_title`. Group keys are stable, e.g.
`com_content.category:9`; other types use their type alias.
`$this->getModel()->getGroupedItems()` exposes arrays of `key`, `title`, `items`.
The view parameter `supjx_group_article_categories` announces the mode.

The package provides data, not site-specific markup. The existing Catichisi
override currently groups by type and must adopt these keys to display category
groups. [The separate patch](template-category-groups-5.0.0.patch) targets its
`html/com_tags/tag/default_items.php`; it is **not applied to Catichisi**.
It preserves card rendering and uses stable `data-load-group` keys. When grouping
is enabled, category wrappers take precedence over the single-content-type rule.
Verify the patch against the current template before applying in that project.

Smart loading must append items to an existing element with the same
`data-load-group` key and append a new group only when missing. Counts in the
patch remain loaded-item counts, not per-category database totals. No independent
pagination or total count per group is introduced.

## Validation and rollout

Dependency-free suites cover structural canonicalization, 704 exhaustive tag
membership fixtures in exact/descendant modes, limits, state boundaries,
query filters, cache registration and page-size contracts.
The read-only local runner passes 60 checks against 191 baseline tagged items and additionally exercises real Joomla/database models,
generated pagination, feeds, grouping, nested namespaces and canonical rebuilds.
Temporary menu fixtures and candidate sources exist only within the PHP process;
no package installation or content/menu configuration is persisted.

These are isolated fixtures and real runtime/model/router checks, **not**
installed-package HTTP frontend or browser verification. Install the candidate
ZIP on local/staging and verify the administrator controls and template integration
before release. Production, server configuration and Catichisi source are untouched.

## Standalone tag filter counts

On a standalone tag page with additional filters enabled on its canonical
SmartTag Pages menu, call `$this->getModel()->getFilterCounts($targets)` from
the tag view override. Pass an array keyed by the template's option identifiers.
Each value is the **complete target expression** (string or `FacetExpression`)
that you pass to `TagFilterRoute::get()`. Use an empty string to clear extra
filters. The result uses the same keys with integer totals, including zero.

Build the target expressions from the current selection and your toggle logic.
If clicking a selected option removes it, pass the expression after removal.
The API uses the current page's base tag, canonical menu, language, identity
and content restrictions. Carry the current public content restrictions
(`content-types` and `content-category`) into the URLs. Counts are for this
context and apply regardless of the link's page size or offset.

```php
use SuperSoft\Plugin\System\TagRouting\Routing\TagFilterRoute;

// These are complete expressions computed by the template's toggle logic.
$targets = [
    'add-secondary' => 'topics+age-group/secondary',
    'remove-topics' => 'age-group/secondary',
    'clear-all' => '',
];
$model = $this->getModel();
$counts = $model->getFilterCounts($targets);
$params = $model->getState('params')->get('supjx_query_filters', []);

foreach ($targets as $key => $expression) {
    $url = TagFilterRoute::get($tagId, $itemId, $expression, $params);
    $total = $counts[$key];
    // Render this URL and its total together.
}
```

`$tagId` and `$itemId` are the current tag ID and active SmartTag Pages menu
ID. An empty batch returns an empty array. Invalid or out-of-scope expressions
throw `InvalidArgumentException`. Call this API only on the supported tag page
context. It validates each expression with the same limits and taxonomy scope
as incoming routes. Matching content is counted once per content type and
content ID. Equivalent conditions share a count, and each SQL batch shares
the same unpaginated set of eligible items.
