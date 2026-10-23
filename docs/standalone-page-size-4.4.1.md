# Standalone page size — revised 4.4.1 contract

This supersedes the precedence rules in 4.4.0.

- Results per page is last in SmartTag Pages **Options**.
- Default remains **30**. Missing saved settings use 30 without resaving.
- Clearing the field uses Joomla's **global Default List Limit**. Empty string or form-filtered zero represents inheritance. An in-place hint explains this.
- A valid explicit GET `limit` has highest priority, including 0 for native All. When GET is absent, the native selector's explicit POST limit is accepted. Negative, malformed, array and overflowing values are ignored. GET takes priority over POST.
- Without explicit input, the menu value/global fallback is used. A shared saved `global.list.limit` session choice cannot silently override it.
- Explicit limits are carried in pagination links alongside view/language; offsets remain Joomla-owned.
- The native page-size selector visibility setting is respected again. No forced hide.
- Feed limits and native com_tags menu defaults retain their native behavior.

Versions: package 4.4.1, routing 4.2.1, component 1.3.1, TagSelect 1.7.2.

Checks: isolated state tests cover missing/custom/empty/filtered-zero settings, global fallback, URL override/All, invalid input, native posted selection, GET-vs-POST priority, pagination carry, lazy-state recursion, shared params and feed/native/no-menu exceptions. XML confirms the field is last in Options. Actual local Joomla returned 10 articles for `?cat-view=list&limit=10`, with next link `?limit=10&cat-view=list&start=10`. The original local TagModel was restored afterwards with www-data ownership and mode 644. No site data, configuration or production changes were made.
