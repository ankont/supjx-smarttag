# SmartTag Pages content types ? package 4.5.0

Versions: package 4.5.0, routing 4.3.0, component 1.4.0, SmartTag Select unchanged at 1.7.2.

## Menu behavior

**SmartTag Pages ? Options ? Content Type** is Joomla's native multiple UCM content-type selector. Since package 4.5.1 / component 1.4.1 it appears under **Details**, retaining `params.content_types` storage and existing selections. No selection means all types, preserving existing behavior. One or more selections constrain native tagged items, totals, page counts and feeds in that menu namespace. This is not an ACL mechanism: native access/language/publication checks and SmartTag's live publication guard still apply. UCM IDs are installation-specific; do not hard-code the example IDs in templates.

For example, `/tag/` can remain unrestricted, `/article-tags/` can select Articles, and `/category-tags/` can select Content Categories. Each selected menu retains its namespace while navigating the tag hierarchy. A scoped menu always uses its menu route, regardless of the advanced canonical-route switch; it never binds or restricts the generic `/!/` marker. Menus with no selected types keep their existing routing switches. If several menus have the same scope, an explicit matching Itemid is preserved; otherwise language matching and lowest Itemid choose the namespace deterministically.

Landing modes remain independent: virtual root still delegates to a zero-result tag view; empty remains empty; top-level/all-tags still list tags without pruning tags by result counts. Their links inherit the active scope.

Results per page remains last in Options: default 30, empty/zero inherits the Joomla global list limit, valid explicit GET/POST limit takes precedence. Feed size remains Joomla's feed limit, with the same configured content-type scope. This is one combined native pagination, not separate pagination for each result group.

## Routing boundary and native menus

Joomla's native Tags router can replace the active Itemid with a native Tagged Items menu during **component preprocess**, before SmartTag's normal build rules run. Reading that replacement would wrongly turn an unrestricted tag link into an article-only one, or discard an explicitly configured scope.

A narrow native Tags router adapter now captures original native `types`/SmartTag menu context before that substitution. The standalone builder chooses an exact matching type-scope menu. If none exists, it uses the unrestricted namespace (or marker) and preserves the native `types` query restriction. Native Tagged Items menu restrictions are therefore not silently discarded. To make a native menu link point to the general tag page, clear its Select Content Type selection; no special switch is needed.

A scoped URL's menu configuration is authoritative: `?types=` cannot change it. A redundant or conflicting types query on that namespace is removed with Joomla's tainted-route canonical redirect. Unrestricted routes may use native `types` queries. Internal `__tagrouting_scope` is rejected from external input and removed from public output, alongside the existing internal routing keys. This preserves validated category pagination state.

## Consumer contract

`TagRoute::forTag($tagId, $language = null, $itemId = null)` builds a fresh tag URL, without inherited offsets or category facets. By default it follows an active SmartTag namespace. Pass an explicit SmartTag Itemid to choose a different namespace, including an unrestricted one. Native tag-list links are also captured before core preprocess substitutes their Itemid.

Use the model's native Pagination object unchanged. Pagination retains scope, offsets, explicit limit and language. `cat-view` is added only from actual GET input, not a preference copied into merged input from the session. View-switch links may retain the current scope/URI and explicit presentation parameters; remove all `__tagrouting*` keys. When changing filters, tags or namespace, start with a fresh builder URI and omit `start`/`limitstart`.

For rendering, the delegated `com_tags/tag` view receives:

```php
$this->params->get('supjx_content_types', []); // normalized configured UCM IDs
$this->params->get('supjx_single_content_type', false); // exactly one configured type
```

The boolean depends on configured scope, never on which types happen to appear on the current results page. Empty or multiple type selections retain the grouped layout, even if one page has results of only one type. Virtual root exposes the same metadata while keeping zero results.

### Catichisi override adaptation

The SmartTag package does not own Catichisi's grouping markup. A minimal patch is provided at [catichisi-single-type-results.patch](catichisi-single-type-results.patch), relative to the Catichisi repository. It was prepared against the current read-only `html/com_tags/tag/default_items.php`; no Catichisi files were edited.

The patch reads `supjx_single_content_type` and conditionally omits only the surrounding `<details>`, summary and `.tag-results__body` wrappers. Item rendering, view switcher, cards, category tiles, fallback items and outer result container stay intact. Apply/review it in the template workspace. The patched PHP passes syntax validation; no visual rendering of this unapplied consumer patch is claimed.

## Verification

- Isolated routing tests: scope selection, type-set normalization, hierarchy order, active/explicit namespaces, delegated root, native restrictions without a matching scope, core Itemid substitution, retained feed/language/offset parameters, query cleanup and canonical round trips.
- Model fixtures: HTML/feed scope, exactly-one metadata, shared-param isolation, page-limit precedence, explicit versus session-derived cat-view.
- Cache fixture: native type filters and presentation join existing tag-view cache keys.
- Existing canonicalization/complexity, membership, publication, taxonomy cache, internal-boundary and faceted pagination suites pass.
- Actual local Joomla 6.2.0 / PHP 8.4.26, read-only model/SQL/router test with **in-memory fixture menu items**: tag `primary` had 191 unrestricted results, 190 Articles, 1 Content Category; combined Articles + Categories returned the same 191. Scoped results/order/totals match the corresponding native result subsets; page two and feeds match their slices. Feed limit remained 10. See [runtime results](local-tag-type-scope-results.json). These are real Joomla models/SQL/routes, not HTTP requests to newly persisted menu items.
- The unbound marker was also tested against a scoped active/home context in the real runtime: it stayed unrestricted with 191 results and `/!/age-group/primary.html`. See [marker results](local-tag-marker-scope-results.json).
- Actual frontend: existing 20 faceted and 11 state-boundary checks pass; another 3 requests verify standalone page-one/page-two pagination without implicit cat-view and a 301 cleanup of injected internal scope. See [faceted results](local-content-type-faceted-results.json) and [standalone results](local-tag-type-frontend-results.json). Scoped HTTP redirects and the new administrator selector were covered by fixtures/source validation, not persisted-menu browser testing.

Reproduce isolated scope/model checks:

```text
php tests/StandaloneContentTypeScopeTest.php
php tests/StandalonePageSizeTest.php
php tests/TagViewCacheSmoke.php
php tests/InternalQueryBoundaryTest.php
php tests/FacetedRoutingContracts.php
```

`tests/local_tag_type_scope.php` is CLI-only, hard-coded to the isolated local snapshot's Itemid 141 and fixture namespaces. Run inside that local container after deploying candidate sources. It writes no configuration or content; menu clones exist only in that process.

Temporary candidate files in the isolated Docker volume were restored with www-data ownership and normal 755/644 permissions after verification. Installation of the built ZIP is still required to apply the package. No production/server crawl controls were changed; these changes do not provide rate limiting.
