# Public tag-page query filters

Package 5.0.0 / routing 5.0.0 adds optional query filters without template controls:

```text
/tag/age-group/primary.html?content-types=com_content.article
/tag/age-group/primary.html?content-types=com_content.article,com_content.category
/tag/age-group/primary.html?content-category=9
```

`content-types` is a comma-separated OR list of registered Joomla UCM aliases,
not installation-specific numeric IDs. Unknown aliases, empty values, arrays,
invalid syntax, more than 32 raw entries or more than 2048 bytes return 404.
Duplicates are removed and aliases sorted in internal state/pagination; no
redirect is introduced. Reordering does not change results.

`content-category` is one positive article category ID, exact membership only
(no descendant categories). It restricts results to `com_content.article`.
Invalid, missing, inaccessible, unpublished, wrong-language categories and
categories under unpublished/inaccessible ancestors return 404. A valid but
contradictory type/category combination returns zero results.

Both filters intersect native/menu scope; they cannot widen it. They apply to
the overridden com_tags tag model, including standalone menu/marker routes,
native tag views and feeds. They do not add faceted grammar to tag pages or
change category routing. Existing live publication/ACL checks remain active.
The native numeric `types` parameter retains its existing behavior.

Filtering happens in SQL before totals, limits and offsets. Model/view cache
keys distinguish these filters. `supjx_query_filters` exposes normalized public
parameters; single-type presentation metadata also reflects a single requested
type or an article category restriction.

Consumer contract:

- Pagination retains both filters and its own offset, explicit limit/view and language.
- View switches retain both filters and the current offset if desired.
- Changing/clearing either filter must remove `start` and `limitstart`.
- To clear a filter, remove its key; do not send an empty value.
- New independent category/tag navigation should start from a fresh route;
  explicitly carry these keys only when retaining the restriction is intended.
- Never copy internal `__tagrouting*` parameters from a browser URI.

No template UI, production or server changes are included.

## Validation

- Dependency-free parser/SQL contract suite: 140 assertions (including the shared 117).
- Page-size/pagination suite: 188 assertions (including the shared 117).
- View-cache registration and live-publication SQL fixtures pass.
- Real local Joomla runtime/database: 60 checks against 191 baseline items,
  covering filtered/unfiltered membership, totals, offsets, feeds, invalid input
  and actual SEF route generation. Candidate classes execute in memory without
  installing the package or modifying content/menu data.
- PHP syntax and nested package ZIP/XML audit pass.

The runtime checks are model/router integration checks, not installed-package
frontend HTTP checks. Frontend rendering and template UI have not been changed.
