# Faceted routing and pagination — 4.3.1

For the strengthened external/internal state boundary and updated consumer contract, see [4.3.2](internal-state-4.3.2.md). The historical input-copy example below must use `FacetedRequest::serialized()` on 4.3.2+.

## Findings

The local frontend is Joomla **6.2.0**, PHP **8.4.26**, at `http://localhost:8089`. Only loopback requests were used. The Catichisi repository, content data, production, `.htaccess`, nginx and robots configuration were not changed. SmartTag PHP files were temporarily copied into the isolated local Joomla Docker volume for integration checks; an original-file backup was retained for restoration.

### Pagination state

The reproduced page `/library/lessons/!/primary,secondary.html` generated `/library/lessons.html?start=30` in its actual HTML. This was not caused by `getPagesLinks()` in the category override.

Joomla 6.2 `Pagination::__construct()` copies only its `paramsFromRequest` allowlist into `additionalUrlParams`. `Pagination::_buildDataObject()` builds fresh `index.php?...&limitstart=...` links from those parameters. `__tagrouting` is not a core pagination parameter. Core `CategoryModel::getItems()` obtains its pagination object from the Articles model; SmartTag's Articles model had not added facet state to that object. Therefore the expression was absent before routing began.

SmartTag's Articles model now adds the resolved current facet state to pagination only in the matching category context. It carries `cat-view` and explicit `lang` for filtered and unfiltered pagination; core parameters such as layout and Itemid continue to be managed by Joomla. Pagination owns `limitstart`/`start`, including first/previous/next-page transitions. The category presentation parameter also participates in the component view cache key.

### Filter changes inherited the old offset

The real second-page HTML also exposed filter-toggle URLs ending in `start=30`. Investigation of the local core `Router::createUri()` established why: passing an **array** to `Route::_()` merges it with all current parsed router variables. SmartTag's public facet builders passed arrays, inadvertently inheriting `limitstart` and potentially other request state.

The builders now pass an explicit `index.php?` URI built from their own query. They preserve their explicit category, Itemid and language arguments, but start a new filter selection without the previous offset. Presentation remains an explicit consumer responsibility. Pagination uses a separate flow and supplies its intended offset.

### Canonical expressions

Previously the parser preserved AND order, OR order and duplicate paths/groups. The incoming rule compared the original expression with that mostly unchanged parser string, so ordering variants never tainted the route. Route builders also emitted the supplied path/group ordering. A missing canonical HTML link was not the underlying cause; the routing state had never selected one deterministic URL.

The common policy is now:

- Validate raw length, raw AND-group count, raw OR count and raw total-option count **before** sorting/deduplication/absorption. All configured `FacetLimits` remain active.
- Sort complete OR paths and AND groups lexicographically; remove repeated paths/groups. Never sort the aliases inside a hierarchical path.
- Resolve against the existing published, accessible, language-scoped taxonomy. Ambiguous aliases still fail. For a resolved tag, choose the shortest scope-unambiguous suffix of its actual parent path. Parent qualifiers remain when needed.
- Apply exact Boolean idempotence and absorption: `A AND A = A`, `A OR A = A`, and `(A OR B) AND A = A`. Different AND/OR structures are otherwise preserved.
- Preserve exact membership semantics. An ancestor tag does **not** imply the child tag, and the child does **not** imply the ancestor tag. The old domain normalizer assumed descendant closure although the SQL uses exact `contentitem_tag_map.tag_id IN (...)`. That mismatch is corrected, rather than extending an unproven equivalence into redirects.

For example, `primary+virgin-mary`, `virgin-mary+primary`, `primary,primary+virgin-mary` and `primary+virgin-mary+primary` select the same canonical expression `primary+virgin-mary`.

Incoming parsing stores the canonical resolved state and marks valid noncanonical routes as tainted. Joomla's existing **System - SEF** plugin rebuilds the route and issues the **301**. A canonical request is not tainted. Existing `start`/`limitstart`, view parameters and language remain in the parsed variable set for the redirect. This relies on the SEF plugin being enabled; no competing custom redirect or canonical-head generator was added.

Public `FacetedRoute::get()` and `forTag()` emit canonical state directly. The component build rule also handles serialized-state consumers with the same scoped canonicalizer. Publication/ACL checks in ArticlesModel, live tag-page publication guards, and taxonomy scope checks remain active.

## Consumer contract

No Catichisi patch is required for the reported pagination call:

```php
echo $this->pagination->getPagesLinks();
```

Keep the pagination object returned by the category model. If a consumer constructs its own Pagination object, it must carry the current validated state before rendering links:

```php
use Joomla\CMS\Factory;
use SuperSoft\Plugin\System\TagRouting\Routing\FacetedRequest;

$app = Factory::getApplication();
$state = FacetedRequest::current();
if ($state !== null && $state['category'] === (int) $category->id) {
    $pagination->setAdditionalUrlParam('__tagrouting', FacetedRequest::serialized()); // 4.3.2+
}
```

For extra consumer-specific presentation parameters, add each explicitly with `setAdditionalUrlParam()` using the appropriate input filter. Never copy the whole current query or attach current `start`/`limitstart` as an additional parameter. `cat-view` and `lang` are already covered by the stock SmartTag Articles model.

For changes/resets, build a **new** route through `FacetedRoute`/the unfiltered category route, then add only the required presentation parameters. Do not merge the complete current query. If consumer code does merge it, remove `start`, `limitstart` and the obsolete `__tagrouting` first. Do not apply this removal to pagination links. Existing `FacetLimits::allows()` checks and disabled-control behavior remain appropriate.

## Verification

Isolated fixtures (not a full Joomla runtime):

```text
php tests/FacetCanonicalizationRegression.php
php tests/FacetedRoutingContracts.php
php tests/FacetLimitRoutingSmoke.php
php tests/FacetedViewCacheSmoke.php
python tests/tag_publication_query.py
```

Canonicalization fixtures exhaustively enumerate all 64 possible tag-membership sets for 11 expressions (704 evaluations), comparing the original and normalized predicates. They cover exact Boolean absorption, different AND/OR structures, ancestor/descendant distinctions, qualified duplicate aliases, invalid/reversed paths, idempotence and raw limits. Routing doubles verify canonical build state, taint/no-taint, pagination metadata and explicit-URI construction; these are not claimed as real Joomla integration tests.

Actual local frontend checks:

```text
python tests/local_faceted_frontend.py --base http://localhost:8089
```

The script is sequential and bounded, rejects non-loopback hosts and off-origin redirects, and does not modify the site. It checks real 301→200 transitions, query preservation, filtered and unfiltered pagination, article-link separation across pages, offset-free filter toggles/reset, and 404 for malformed/over-limit/unknown facets. An optional `--baseline` HTML file compares page-one article links with the original response. It is a site-specific integration check, not a load test.

The completed 2026-10-23 run passed **20 local requests** (10 responses 200, 5 redirects 301, 5 responses 404). Every tested redirect was followed immediately by 200 without another hop. Explicit `lang=el-GR`, `cat-view=list` and `start=30` were retained; this was not a full multilingual-installation test. Filtered page-one article links matched the saved original response and page-two links did not overlap them. Exact observations are in [local-faceted-frontend-results.json](local-faceted-frontend-results.json). A prior run timed out once on the unknown-tag request; a direct check returned 404 and the completed run reproduced that 404 successfully.

After integration testing the original local SmartTag source tree was restored, the temporary canonicalizer file removed, and the restored FacetedRoute/ArticlesModel hashes checked against the backup. Install the delivered package to retain the fixes locally; testing did not silently leave a deployed upgrade.

These changes consolidate URLs and preserve navigation state. They are **not rate limiting or distributed-crawl protection**, and provide no reason to remove the production block.
