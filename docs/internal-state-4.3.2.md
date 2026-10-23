# Internal state boundary and request-scope optimization — 4.3.2

## Cause and fixes

Review input: Catichisi `tools/SMARTTAG-431-LOCAL-REVIEW.md`. Installed baseline: package 4.3.1 / routing 4.1.1, local Joomla 6.2.0. Candidate: package 4.3.2 / routing 4.1.2.

Two distinct defects were confirmed in SmartTag. The standalone query cleaner omitted `__tagrouting`; feeds and other generic builders could inherit it. Separately, `FacetedRequest::current()` decoded the merged application input without proving it came from successful scoped route parsing. Valid-looking JSON was not a sufficient trust boundary.

The plugin now strips client-supplied `__tagrouting` and `__tagrouting_tag` before site parsing. GET/HEAD requests carrying either reserved key are tainted and Joomla System - SEF issues a clean 301. No attempt is made to decode external JSON, escaped JSON or array payloads. The canonical SEF path remains the public representation of filters.

The scoped faceted parser records its successfully resolved state in an application-keyed WeakMap, after raw limits and taxonomy/ACL/language validation. `FacetedRequest::current()` reads this record only in the matching content-category context. It does not read JSON from query, POST or merged input. The serialized payload is decoded once for the record and is exposed separately as `FacetedRequest::serialized()` for trusted in-process pagination consumers. It is reset at site initialization and is not session state.

A final site build rule strips leftover internal metadata **after** component routing. Category pagination first converts its validated state into `/category/!/expression`; the final boundary does not delete its already-built filter path or offset. Standalone builders also explicitly remove the reserved keys, while TagRoute now starts from a fresh explicit URI to avoid inheriting category state/offset. The boundary protects feeds and generic `Route::_()` consumers as well as SmartTag's own helpers.

The redirect mechanism continues to rely on Joomla's enabled System - SEF plugin. Consumers must still avoid rendering arbitrary raw query parameters themselves.

## Consumer contract

| Operation | Keep | Drop / source |
|---|---|---|
| Change view on the same page | Current SEF path, allowlisted `cat-view`, `lang`, layout, and current offset if retaining the same filtered result page is intended | Always drop `__tagrouting`, `__tagrouting_tag`, obsolete view aliases and unrelated modal/preview context |
| Stock category pagination | Model's original Pagination object, validated facet path, Joomla-owned offset, explicit view/language | No template patch to `getPagesLinks()`; never copy raw query JSON |
| Custom category pagination | Validated `FacetedRequest::serialized()` as an **in-process additional URL parameter**, matching category, view/language | Do not take it from `$input->getString('__tagrouting')`; Pagination owns `start`/`limitstart` |
| Add/remove/reset filters | Fresh route built for the new expression/category, explicitly chosen view/language | Old offset, previous internal state and unrelated category/tag context |
| Category → standalone tag / another category | `TagRoute::forTag()` or a fresh category/faceted route, explicitly selected language/view | Previous expression and offset; never merge the entire current request |
| Standalone tag pagination/feed | Target tag, language and consumer's legitimate feed/pagination parameters | No category `__tagrouting` in public links |

For the Catichisi view switcher, the minimal cleanup after cloning/creating its URI is:

```php
$uri->delVar('__tagrouting');
$uri->delVar('__tagrouting_tag');
```

Its existing `library-view` cleanup can stay. Preserve the path and add the intended `cat-view`. For filter/category/tag changes (not same-result pagination), also remove `start` and `limitstart`. Build fresh cross-page routes instead of cloning the current category URI. Prefer an allowlist when carrying any query parameters.

If constructing a separate category Pagination object:

```php
$state = FacetedRequest::current();
if ($state !== null && $state['category'] === (int) $category->id) {
    $pagination->setAdditionalUrlParam('__tagrouting', FacetedRequest::serialized());
}
```

This is routing metadata passed to PHP's route builder, **not** permission to publish `?__tagrouting=...` URLs. With stock model pagination, SmartTag does this automatically. Existing raw complexity checks and disabled-control behavior are unchanged.

## Optimization and measurement

Previously incoming parsing/component building, public facet builders and the Articles model owned separate taxonomy resolver caches. The resolver's two SELECTs were repeated for the same category/access/language scope. A wildcard language from a builder could also produce a separate `*` lookup even though the page was Greek.

Package consumers now share one resolver per application/database object; cache entries retain category ID, normalized authorized view levels and language. Site initialization clears the application cache. The SQL and published/access/language predicates are unchanged. Null/wildcard builder language uses the current request language for scope queries, without forcing a language query parameter into public URLs. No shared session, cross-user or persistent response cache was introduced.

Measured on the actual local frontend, with temporary instrumentation of successful `CategoryTaxonomyResolver` SELECT loads only:

| Request | Baseline scope SELECTs | Candidate scope SELECTs | Results |
|---|---:|---:|---|
| `christmas,easter+life-of-jesus,parables+primary,secondary` | 8 | 4 | Empty results in both |
| `life-of-jesus,parables+primary,secondary` | 10 | 4 | Same 19 article links, in the same order; same 4 active filter options |

The nonempty request called `resolve()` 288 times in both versions. The reduction is in executed taxonomy SELECTs, not in the number of links or the entire database query count. See [recorded comparison](local-scope-optimization-results.json). Single-request wall times were approximately 1.287 s baseline and 1.284 s candidate: **no material latency improvement is established**. Initial requests during Docker source swaps could still execute old OPcache code; those transition samples were excluded after waiting for revalidation. This is neither a load test nor a concurrency/capacity measurement.

The remaining two SELECTs belong to the template's independently constructed resolver. Optional consumer adaptation can share the same cache:

```php
$resolver = CategoryTaxonomyResolver::forApplication(
    $app,
    Factory::getContainer()->get(DatabaseInterface::class),
);
$scope = $resolver->resolve($categoryId, $authorizedViewLevels, $language);
```

This replaces a `new CategoryTaxonomyResolver(...)` at the consumer. It is optional, requires 4.3.2+, and was **not applied** to Catichisi. No additional query saving from that unmodified consumer is claimed.

## Verification and limitations

- Real frontend: all 20 pagination/canonicalization regression requests passed again with the new boundary.
- Real frontend boundary suite: 11 requests passed, including valid JSON, escaped JSON, empty/malformed input, single-hop clean redirects, identical clean tag hrefs (feeds/hierarchy/view switches), and filtered category pagination despite an injected contradictory payload. No rendered href contained either internal key.
- Real database, read-only CLI: fresh versus shared resolver returned identical 87-node scopes for `[1]` and `[1,5]` across `el-GR` and `en-GB`. This snapshot has the same nodes for those contexts, so it does not by itself prove distinguishing ACL/language isolation.
- Isolated cache fixtures use public/private, Greek/English and unpublished nodes, and verify identical uncached/cached results, reduced query loads, separate application/database/category/ACL/language keys, and explicit reset.
- Isolated state tests reject external input without a recorded parse, keep pagination state after mutable input is tampered with, and ensure route builders do not inherit category state into standalone tag links.
- Existing exact-membership, publishing, limit and canonical regression suites remain active.

Commands:

```text
php tests/InternalQueryBoundaryTest.php
php tests/TaxonomyRequestCacheTest.php
php tests/FacetedRoutingContracts.php
php tests/FacetCanonicalizationRegression.php
php tests/FacetLimitRoutingSmoke.php
php tests/FacetedViewCacheSmoke.php
python tests/tag_publication_query.py
python tests/local_internal_boundary.py
python tests/local_faceted_frontend.py
```

Temporary profiling runs and PHP candidate files were limited to the isolated local Docker volume. Original local source files are restored after verification, including www-data ownership, directory mode 755 and file mode 644. No Catichisi repository, content data, production, `.htaccess`, nginx or robots configuration was changed. Install the package to apply the upgrade permanently.

Canonicalization, state cleanup and fewer per-request queries are **not distributed-crawl protection**. The production block is unchanged; restoring filters requires separate operational review and human monitoring.
