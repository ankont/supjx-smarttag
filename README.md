# System - Clean Tag Routing

A reusable Joomla 5/6 system plugin with three independently configurable routing features. It modifies no Joomla core files, creates no component, and renders no landing-page content.

## Routing features

The standard **Plugin** tab exposes three Joomla switchers:

| Feature | Upgrade/default value | Responsibility |
|---|---:|---|
| Canonical cleanup | On | Removes redundant tag/type/language filter state when an exact Joomla menu item already represents it. |
| Hierarchical tag URLs | Off | Adds canonical normal `com_tags` pages below a configurable prefix. |
| Faceted category filters | On | Adds explicit grouped tag filters below `com_content` category routes. |

Cleanup and faceting default to On because they existed before these settings were introduced. Hierarchical tag URLs default to Off because enabling a new top-level namespace can conflict with an existing menu alias. Missing parameters on an upgraded installation receive these same compatibility defaults.

Every combination is supported, including all features Off. A disabled feature attaches no related rule, adds no related state, and does not change its URLs.

## A. Canonical cleanup

When generated Joomla state exactly matches a menu item, redundant public variables are removed and the real menu route is used:

```text
/life/a1.html?types[0]=1&tag_list_language_filter=el-GR
→ /life/a1.html
```

`MenuTagContextResolver` compares normalized tag groups and recognized filter state. `CanonicalMenuRules` handles `com_content`; `GenericMenuBuildRule` covers the site router and `com_tags`. Disabling **Canonical cleanup** leaves normal Joomla menu selection and query output untouched.

## B. Hierarchical Joomla tag pages

Enable **Hierarchical tag URLs** to expose normal Joomla `com_tags` tag views under **Tag URL prefix**. The default prefix is one safe segment, `tags`, stored without slashes:

```text
/tags/christmas
/tags/series/pame-ekklisia
/tags/theme/bible/parables
```

Changing the prefix to `topics` changes generated and resolved URLs to `/topics/...`. Invalid multi-segment or unsafe values fall back to `tags` at runtime.

These routes render the standard `com_tags` `tag` view. The plugin does not redirect to articles, provide landing-page fields, duplicate `com_tags`, or add content-source logic.

### Exact hierarchy resolution

The path must begin at a real root-level Joomla tag (`parent_id = 1`). Every following alias is resolved only as a direct child of the previously resolved tag:

```text
Theme
└── Bible
    └── Parables

/tags/theme/bible/parables
```

If another branch also contains `parables`, it does not compete with this path. Missing, inaccessible, unpublished, wrong-language, non-rooted, or ambiguous paths fail with Joomla's normal 404; the resolver never guesses.

Parsing uses one alias-set query rather than one query per segment. Building by ID loads the target and its nested-set ancestor chain, with request-level caches for repeated paths and IDs.

### Menu and multilingual context

A dedicated menu item is not required. The parse rule sets ordinary Joomla state:

```text
option=com_tags
view=tag
id[]=<resolved tag ID>
```

If an accessible language-compatible exact tag menu item exists, it is used internally for module/template context; otherwise a generic root `com_tags` tags item may be used. Neither `Itemid` nor the menu route becomes part of the canonical public path. With no suitable item, normal `com_tags` rendering still works through the component state.

Language Filter prefixes are preserved by Joomla. Published state, view access, tag language, SEF suffix, rewrite mode, and the current language are respected.

### Canonical generation and redirects

When the feature is enabled, generated single-tag `com_tags` routes are marked before the core component router runs and rewritten after Joomla has selected its language/menu context. The public result always uses the configured hierarchical prefix.

Existing inbound Joomla/menu tag URLs outside that namespace remain Joomla routes and are not forcibly redirected. This avoids redirect loops and preserves established menu URLs. Inside the configured namespace, redundant routing query remnants can use Joomla's tainted-route canonical redirect on safe GET requests. Invalid hierarchy always produces 404 instead of a redirect or fallback.

Other code can request a canonical tag-page URL by ID:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\TagPageRoute;

$url = TagPageRoute::get($tagId, $language);
```

When the feature is disabled, this API returns Joomla's ordinary `com_tags` route and no hierarchical build rule runs.

## C. Faceted category routing

Faceted routes remain a separate grammar below a real `com_content` category route:

```text
/selections/seasonal/!/christmas
/lessons/!/a2/math
/lessons/!/a2,a3/math
/lessons/!/a2,a3+math
```

Everything before `!/` is resolved exclusively by Joomla. Everything after it is a virtual tag-filter expression:

```text
/   hierarchy inside one tag path
,   OR between alternative tag paths
+   AND between filter groups
```

Therefore:

```text
a2/math       = the hierarchical tag A2/Math
a2,a3/math    = A2 OR A3/Math
a2,a3+math    = (A2 OR A3) AND Math
```

The formal grammar is intentionally limited and has no parentheses or NOT:

```text
expression := group ("+" group)*
group      := path ("," path)*
path       := alias ("/" alias)*
```

The canonical delimiter is `/!/`. Input aliases `/!filter/` and `/!tag/` remain accepted, but generated/canonical URLs always use `/!/`. Bare `/filter/`, `/tag/`, or unmarked tag paths never activate faceting.

Category-assigned tags define the permitted taxonomy roots for this feature only. The custom `ArticlesModel` uses `IN (...)` for OR inside a group and one correlated `EXISTS` per AND group. Every serialized ID is revalidated against live publication, access, language, and category taxonomy state before SQL is extended.

### Faceted public API and current state

Templates build resolved paths without concatenating aliases:

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
```

The normalized current state remains available through:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest;

$state = FacetedRequest::current();
```

It is stored internally as versioned JSON under `__tagrouting`, containing category ID, canonical public expression, normalized effective ID groups, and resolved ID/alias/root metadata.

## Two different URL schemes

```text
/tags/series/pame-ekklisia
```

is the Joomla page of the `Series > Pame Ekklisia` tag itself.

```text
/selections/series/!/pame-ekklisia
```

is a faceted filter applied inside the real `/selections/series` content category. The `/tags/` and `/!/` grammars are independent and never fall back into each other.

## Architecture

- `Extension/TagRouting` is the feature-aware coordinator.
- `Configuration/RoutingFeatures` owns defaults and safe prefix normalization.
- Cleanup uses `CanonicalMenuRules`, `GenericMenuBuildRule`, and the `Menu` helpers.
- Hierarchical tag pages use three site rules (`PrepareBuild`, `Parse`, `Build`), `TagHierarchyRepository`, `TagHierarchyResolver`, and `TagMenuContextResolver`.
- Faceting remains in its component rules, domain expression objects, category taxonomy resolver, request serializer, and model overlay.
- The custom `com_content` router is registered only for cleanup and/or faceting. The MVC model overlay is registered only for faceting.

This follows Joomla's supported system-plugin router-rule and component-router extension patterns. It does not parse `REQUEST_URI`, patch core components, or copy component rendering.

## Installation and development

Run:

```text
powershell -ExecutionPolicy Bypass -File build/build.ps1
```

Install `build/plg_system_tagrouting-v3.0.1.zip`, enable **System - Clean Tag Routing**, and configure its routing features in the standard **Plugin** tab. The tested target is Joomla 5/6 with PHP 8.3 or later.

For PHPUnit:

```text
composer install
composer test
```

Without Composer:

```text
php tests/run.php
```

The dependency-free suite covers all eight feature combinations, compatibility defaults, prefix validation, exact hierarchy and duplicate aliases, invalid paths, faceted grammar, normalization, serialization, delimiter aliases, and menu-state comparison.

## Intentional limitations

- `/tags` without a tag path is not supplied as a custom all-tags landing page.
- Hierarchical tag pages support one tag per page; native multi-tag `com_tags` views remain Joomla routes.
- Existing non-prefix Joomla tag URLs are not globally redirected.
- Faceted expressions support positive AND-of-OR groups only; no parentheses or NOT.
- A configured prefix intentionally owns that top-level namespace while the feature is enabled, so administrators should avoid a conflicting menu alias.
- End-to-end module assignment, web-server rewrite, database SQL, and multilingual redirect behavior require testing on a real Joomla site; unit tests cover the isolated decisions and resolvers.
