# Standalone tag page size — 4.4.0

Historical behavior: see [4.4.1](standalone-page-size-4.4.1.md) for the revised empty-field/global fallback and explicit-limit precedence.

SmartTag Pages menu item **Options → Results per page** now defaults to **30** total results per page. The field accepts 1–500; zero/negative stored values fall back to 30. Existing menu items inherit 30 without being resaved.

This applies to native `com_tags/tag` pages whose active menu context is `com_tagrouting`, view `tags`, regardless of whether the public URL uses a menu route or standalone marker. It is one common pagination across content types. A tag page attached to a native com_tags menu item retains Joomla's existing behavior. Feeds and the bare menu landing page are unaffected.

The TagModel applies the menu value after native state population, overriding the global list limit, the shared saved session limit and incoming `limit` parameter. The display parameters are cloned rather than mutated globally. The native page-size selector is hidden since it cannot override the menu limit. Pagination retains `cat-view` and explicit `lang`; Joomla owns its offsets and totals. Existing publishing and ACL query checks remain in place.

No custom pagination rendering or template patch is required. The component is 1.3.0, routing plugin 4.2.0, package 4.4.0; TagSelect remains 1.7.2.

Validation:

- `php tests/StandalonePageSizeTest.php`: default/custom/invalid/oversized settings, global/session/URL precedence, lazy-state recursion protection, offsets, shared-parameter isolation, feed/native-menu/no-menu exceptions.
- Actual local Joomla 6.2: `/tag/age-group/primary.html?cat-view=list&limit=500` returned 200 with 30 article elements and page-two links using `start=30`.
- `/tag/age-group/primary.html?cat-view=list&start=30&limit=0` returned 30 article elements, previous/first links without an old offset and next links using `start=60`; `cat-view=list` survived.
- The first runtime attempt exposed recursive lazy state population; the implementation was corrected to read the already-populated protected state directly and the isolated test now guards against recursion.

Only TagModel was temporarily replaced in the local Docker volume. The original local file was restored after testing with owner/group www-data:www-data and mode 644. No menu data, site configuration, Catichisi repository or production settings were changed. Install the built package to apply the new menu option and behavior permanently.
