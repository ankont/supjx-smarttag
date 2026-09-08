# SuperSoftJx - SmartTag

A Joomla 5/6 suite for structured tag selection, deterministic hierarchical tag routing, and menu-aware tag pages. Version 4 installs three extensions:

- `plg_system_tagrouting`: routing, canonicalization, tag resolution, faceted filtering, public route APIs, request state, and breadcrumbs.
- `plg_fields_tagselect`: the **SmartTag Select** custom-field type, with branch scoping, optional native Joomla-tag storage, leaf-only selection, and controlled tag creation.
- `com_tagrouting`: the lightweight **SUPJX Tag Pages** menu item type used only for Joomla `Itemid`, template/Gantry, module, language, and optional canonical-base context.

The three extensions are packaged together but remain operationally independent. SmartTag Select does not require the routing feature to render or save a field, and routing does not require a SmartTag Select field. The component never replaces `com_tags`, selects a tag, or renders a tag landing page. Valid standalone routes always resolve to the normal Joomla `com_tags` tag view and existing template overrides.

## Source and package structure

```text
package/
├── pkg_smarttag.xml
├── components/com_tagrouting/
├── plugins/fields/tagselect/
└── plugins/system/tagrouting/
```

The build creates three constituent ZIPs and wraps them in one installable package:

```text
build/output/pkg_smarttag_v4.0.0.zip
```

The internal identities remain `system/tagrouting`, `fields/tagselect`, and `com_tagrouting`. Installing the package therefore upgrades existing standalone copies instead of creating duplicates or losing their saved parameters and custom fields. Each extension keeps its own meaningful release line:

| Extension | Version in this package |
|---|---:|
| `pkg_smarttag` | 4.0.0 |
| `plg_system_tagrouting` | 4.0.0 |
| `plg_fields_tagselect` | 1.7.0 |
| `com_tagrouting` | 1.0.0 |

## SmartTag Select custom field

After installation, enable **Fields - SuperSoftJx - SmartTag Select** and create a Joomla custom field of type **Tag Select**. The field can either store an independent custom-field value or act as a controlled editor for the item's native Joomla tags. Its field options include:

- all tags, selected hierarchy branches, or all except selected branches;
- searchable AJAX or nested-tree selection;
- optional leaf-only selection and single/multiple values;
- controlled creation of new tags;
- automatic frontend output as linked tags or plain text.

When native tag handling is used, the plugin updates only the subset managed by that field and preserves unrelated native tags on the item. Existing `tagselect` fields continue to use the same field type and stored configuration after migration into the package.

## Routing features

The plugin's standard **Plugin** tab keeps three independent feature switches:

| Feature | Upgrade/default | Responsibility |
|---|---:|---|
| Canonical cleanup | On | Removes redundant Joomla tag/type/language query state when an exact menu item already represents it. |
| Hierarchical standalone tag URLs | Off | Resolves standalone hierarchical tag pages through a Tag Pages menu route or a marker route. |
| Faceted category filters | On | Filters one real `com_content` category with a hierarchical tag expression. |

All eight combinations remain supported. A disabled feature attaches no corresponding routing rules, state, redirects, or breadcrumb behavior.

## SUPJX Tag Pages menu item

Create a menu item of type **SUPJX Tag Pages**. It represents no particular tag and has no tag selector. Its full Joomla menu route is an explicit standalone namespace boundary.

Examples:

```text
Tag Pages item route: /tags
/tags/age-group/secondary/gymnasium.html

Nested Tag Pages item route: /menu-a/menu-b/tags
/menu-a/menu-b/tags/age-group/secondary/gymnasium.html
```

Parent menu items may point to content categories; that does not change the meaning. The actual `com_tagrouting` menu item is authoritative, so everything after its full route is a standalone tag hierarchy, never a faceted category expression.

### Use menu route as canonical URL

The menu item's advanced option defaults to **Yes**.

- **Yes:** its full route becomes the canonical base for standalone tag pages. The marker form is still accepted as an alternate/fallback and can canonicalize to the menu form on safe GET/HEAD requests.
- **No:** the item supplies `Itemid`, Gantry/template assignment, module assignment, and language context only. The public canonical URL remains marker-based.

For example, a hidden `/tags` item with the option set to **No** supplies internal context while the public canonical URL remains:

```text
/!/age-group/secondary/gymnasium.html
```

No numeric Itemid is configured in the plugin. It automatically prefers a Tag Pages item in the current language and falls back to an All Languages item.

## Standalone tag routes

Both forms below express the same standalone tag intent:

```text
/!/age-group/secondary/gymnasium.html
/tags/age-group/secondary/gymnasium.html
```

The first is the deterministic root-marker form. The second is available only when `/tags` is the full route of a real SUPJX Tag Pages menu item. With no suitable menu item, the canonical standalone form uses the configured canonical marker.

Resolution is strictly parent-to-child:

```text
Age Group
└── Secondary
    └── Gymnasium
```

`secondary` resolves only beneath the resolved `age-group`, and `gymnasium` only beneath that `secondary`. Duplicate aliases in other branches do not compete. Invalid, inaccessible, unpublished, wrong-language, non-rooted, or ambiguous paths return Joomla's normal 404; no global alias guessing occurs.

## Faceted category routes

A real category before the marker changes the intent to category filtering:

```text
/selections/!/age-group/secondary/gymnasium.html
```

This means: render the Joomla content category represented by `/selections`, filtered by the resolved tag expression. It is not a standalone tag page.

The expression grammar remains:

```text
/   hierarchy inside a tag path
,   OR between alternative paths
+   AND between groups
```

Examples:

```text
/lessons/!/grade/a2/math
/lessons/!/grade/a2,grade/a3
/lessons/!/grade/a2,grade/a3+subject/math
```

The last example means `(A2 OR A3) AND Math`. Parentheses and NOT remain intentionally unsupported. Category-assigned tags define the permitted taxonomy roots, and every incoming ID is revalidated against live publication, access, language, and category scope before the content query is extended.

The plugin never globally converts `com_tags + catid` into faceted routing. Link intent is selected where the link is created.

## Routing markers

The plugin accepts any enabled combination of these built-in marker segments:

- `!`
- `!tag`
- `!filter`
- one optional custom marker beginning with `!`

**Canonical routing marker** chooses the single marker generated by both standalone fallback URLs and faceted category URLs. It must be enabled; malformed legacy or manually edited settings safely fall back to an enabled marker. Defaults preserve the previous behavior: all three built-ins are accepted and `!` is canonical.

The marker always means “virtual tag-routing information starts here.” Context before it determines the intent:

```text
/!/age-group/secondary
    standalone tag page

/selections/!/age-group/secondary
    faceted filtering within /selections
```

Bare `/tag/` and `/filter/` are never markers.

## Breadcrumb hierarchy

After Joomla dispatches the real component, the plugin appends the exact resolved hierarchy to its pathway. The independent **Breadcrumb tag marker** is `#` by default and may be blank.

Faceted example:

```text
Home / Selections / # / Age Group / Secondary / Gymnasium
```

Intermediate tag links remain faceted inside the same category. Standalone breadcrumbs instead use standalone tag routes for every intermediate level. If a filter expression contains multiple OR alternatives or AND groups, the plugin leaves Joomla's category breadcrumb unchanged because such an expression has no truthful single linear ancestor chain.

## Public route APIs

Templates and helpers should never assemble URLs manually.

Standalone route from a tag ID:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\TagRoute;

$url = TagRoute::forTag($tagId, $language);
```

The router chooses the canonical menu or marker base and applies the appropriate internal menu context. The previous `TagPageRoute::get()` entry point remains as a compatibility alias.

Faceted route from a category ID and tag ID:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRoute;

$url = FacetedRoute::forTag($categoryId, $tagId, $itemId, $language);
```

This resolves the tag's full hierarchy inside the category's permitted taxonomy scope and starts directly from `com_content`; it does not create `/tags/...?...catid=` URLs.

Advanced callers may continue to use `FacetedRoute::get()` with a normalized `FacetExpression`. Current resolved faceted state remains available through:

```php
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest;

$state = FacetedRequest::current();
```

## Canonical cleanup

The independent cleanup feature retains existing behavior. When Joomla-generated filter state exactly matches a menu item, redundant parameters are removed:

```text
/life/a1.html?types[0]=1&tag_list_language_filter=el-GR
→ /life/a1.html
```

Disabling cleanup leaves normal Joomla menu selection and query output untouched.

## Upgrade notes from standalone extensions and SmartTag Routing 3.x

- Install the v4 **SmartTag package**, not either standalone plugin ZIP.
- The existing `system/tagrouting` record and saved feature toggles are retained.
- The existing `fields/tagselect` record, custom fields, values, and field parameters are retained.
- Enable each plugin independently after a fresh installation. An already enabled child plugin remains enabled when upgraded.
- The old textual **Tag URL prefix** setting is removed.
- A previous `/tags/...` path is a standalone route only after creating a SUPJX Tag Pages menu item whose actual full route is `/tags`.
- Without that menu item, standalone routes use the canonical marker, `/!/...` by default.
- Hierarchical standalone routing remains Off by default for installations that never saved the setting; cleanup and faceting remain On.

## Installation and development

Build on Windows with:

```text
powershell -ExecutionPolicy Bypass -File build/build.ps1
```

Install `build/output/pkg_smarttag_v4.0.0.zip`. Enable **System - SuperSoftJx - SmartTag Routing** for routing features and **Fields - SuperSoftJx - SmartTag Select** wherever the custom field type is needed. Configure routing in the system plugin's **Plugin** tab, and create a **SUPJX Tag Pages** menu item if menu-aware standalone context is desired.

Tests:

```text
composer install
composer test
```

or without Composer:

```text
php tests/run.php
```

The dependency-free suite covers feature isolation, compatibility defaults, configurable markers, strict hierarchy and duplicate aliases, nested menu boundaries, faceted grammar, normalization, serialization, and menu-state comparison. End-to-end component dispatch, SEF redirects, module assignments, and multilingual menu behavior still require verification on a real Joomla installation.

## Intentional limitations

- The bare SUPJX Tag Pages menu route has no landing-page renderer and returns 404 without a tag path.
- Standalone pages represent one tag; native multi-tag `com_tags` views remain ordinary Joomla routes.
- Compound AND/OR filters do not receive a misleading linear tag breadcrumb.
- Existing non-marker `com_tags` routes are not globally redirected.
- Faceted expressions support positive AND-of-OR groups only; no parentheses or NOT.
- If several SUPJX Tag Pages items exist for the same language, the lowest Itemid is the deterministic context/canonical default; use one such item per language when possible.
