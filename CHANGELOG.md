# Changelog

## 4.5.1 - 2026-10-23

- Moved SmartTag Pages Content Type from Options to Details, matching the placement of native Tagged Items selection fields. Storage remains `params.content_types`, preserving existing selections and routing behavior.
- Component 1.4.1; routing remains 4.3.0 and SmartTag Select remains 1.7.2.

## 4.5.0 ? 2026-10-23

- Added native multiple Content Type selection to SmartTag Pages menu Options. Empty remains all types; a scoped menu namespace constrains native tag results, totals, pagination and feeds while retaining existing visibility checks.
- Preserve scope through hierarchy navigation and capture native Tagged Items restrictions before core preprocess/menu cleanup changes Itemid. If no exact scoped namespace exists, retain native `types` query filters on the general route.
- Scoped menu routes remain distinct from the unrestricted root marker. Client-supplied internal scope metadata is stripped; redundant `types` on scoped routes receives Joomla's clean canonical redirect.
- Expose `supjx_content_types` and `supjx_single_content_type` for overrides. Include a separate consumer patch to omit result grouping wrappers when exactly one type is configured.
- Pagination carries `cat-view` only when explicitly present in GET, rather than copying a session-derived preference. Register native type filters in tag view cache keys.
- Component 1.4.0, routing 4.3.0; SmartTag Select remains 1.7.2.

## 4.4.1 — 2026-10-23

- Moved Results per page to the end of SmartTag Pages Options. Empty values inherit Joomla's global list limit, with an inline hint; missing values still default to 30.
- Explicit valid URL limits take precedence and survive pagination, including `limit=0` for All. Shared session values do not override the menu/global default; the native selector visibility setting is respected again.
- Updated component to 1.3.1 and routing to 4.2.1.

## 4.4.0 — 2026-10-23

- Added Results per page to SmartTag Pages menu Options, defaulting to 30 total items with one native pagination. The menu limit overrides global/session/URL page-size values for standalone tag pages using that context; the native page-size selector is hidden.
- Preserved feed limits, native com_tags menu items and menu landing pages. Existing SmartTag menu items inherit 30 without being resaved.
- Updated the component to 1.3.0 and routing plugin to 4.2.0.

## 4.3.2 — 2026-10-23

- Strip client-supplied internal routing keys before parsing and remove leftover metadata after route building, including standalone tags and feeds. Safe polluted requests use Joomla SEF's single-hop clean redirect.
- Read current facets and pagination metadata only from application-scoped validated parse state, never from external JSON input. Added `FacetedRequest::serialized()` for internal PHP consumers.
- Share taxonomy scope caches per application/database while retaining category, ACL and language keys; normalize wildcard builder languages to the current language for scope queries. TagRoute now builds from a fresh explicit URI.
- Updated routing to 4.1.2. See the 4.3.2 boundary/optimization review for consumer contracts and measured local results. Production crawl protection is unchanged.

## 4.3.1 — 2026-10-23

- Preserve faceted request state, category view mode and explicit language in native category pagination. Filter builders now use fresh explicit query URIs, preventing Joomla's array-route merge from inheriting the current offset.
- Canonicalize AND/OR order, repeated paths/groups and exact Boolean absorption after raw complexity validation. Scope-resolved paths use the shortest unambiguous spelling; hierarchy segment order is preserved. Noncanonical incoming URLs use Joomla SEF's tainted-route 301 mechanism.
- Corrected domain normalization to respect exact tag-map membership: descendant tags no longer incorrectly imply ancestor tags. Existing ACL, publication, language and taxonomy scope checks remain in place.
- Updated routing to 4.1.1. These routing fixes do not provide rate limiting or justify removing server-level crawl protection.

## 4.3.0 — 2026-10-23

- Added configurable faceted limits: 10 AND groups, 10 OR options per group, 15 options total and 1024 expression bytes by default. Incoming over-limit routes return 404 before taxonomy queries; route generation returns an empty string for over-limit expressions.
- Added the shared `Configuration\FacetLimits::fromPlugin()->allows($expression)` API for template controls. Hierarchical paths count as one option; standalone tag pages are unaffected.
- Updated the routing plugin to 4.1.0.

## 4.2.5 - 2026-10-19

- Restored the localized plugin-group prefix in the SmartTag Select display name in both English and Greek language files.
- Updated the independently versioned tag-select plugin to 1.7.2.

## 4.2.4 — 2026-10-07

- Added live publication and access checks to native tag-page queries for articles and content categories, including publication dates and category ancestors. This prevents stale UCM records from exposing unpublished native content through SmartTag standalone pages.
- Updated the routing plugin to 4.0.6. Clear existing Joomla tag-view and page caches after installation.

## 4.2.3 - 2026-10-03

- Fixed native tag selections saved through Joomla frontend article forms, including the SmartBrowser editor, by normalizing the save context with Joomla's FieldsHelper.
- Refreshed tag-tree and scope caches after tag creation and before native tag merging so newly created tags can be selected on the first save.
- Updated the independently versioned tag-select plugin to 1.7.1.

## 4.2.2 — 2026-10-03

- Registered the serialized faceted request in Joomla's content view cache parameters, separating unfiltered categories and different tag expressions even when they share an Itemid.
- Updated the independently versioned routing plugin to 4.0.5. Clear existing Joomla content caches after installing this fix.

## 4.2.1 — 2026-09-10

- Removed the component-rendered virtual-root heading so the delegated `com_tags/tag` override remains the single owner of the page heading.
- Retained the configured title and icon in the `supjx_virtual_root_title` and `supjx_virtual_root_icon` view parameters.
- Updated the independently versioned context component to 1.2.1.

## 4.2.0 — 2026-09-10

- Added configurable title and icon fields to the virtual tag-root menu page, defaulting to `Φίλτρο` and `fas fa-filter`.
- Exposed the configured values to native `com_tags` template overrides as `supjx_virtual_root_title` and `supjx_virtual_root_icon`.
- Updated the independently versioned context component to 1.2.0.

## 4.1.5 — 2026-09-10

- Added an explicit breadcrumb-marker visibility switch so administrators can reliably remove the marker even when Joomla restores the text field's default value.
- Updated the routing plugin to 4.0.4.

## 4.1.4 — 2026-09-10

- Replaced the premature site build rule that produced `/?Itemid=...` with a proper `com_tagrouting` component router, preserving the menu alias while removing `view=tags`.
- Updated native `html/com_tags/tag` override discovery for the virtual root.
- Updated the routing plugin to 4.0.3 and context component to 1.1.4.

## 4.1.3 — 2026-09-10

- Added cleanup for the redundant `view=tags` query on the bare SupJx SmartTag Pages menu route.
- Prevented the current language from leaking as a `lang` query parameter in standalone breadcrumb links when no language prefix is needed.
- Restored normal `html/com_tags/tag` template-override discovery for the delegated virtual-root view.
- Updated the independently versioned routing plugin to 4.0.2 and context component to 1.1.3.

## 4.1.2 — 2026-09-10

- Registered the native `com_tags` web-asset registry before rendering the delegated virtual-root tag layout.

## 4.1.1 — 2026-09-10

- Fixed the virtual-root tag view by injecting Joomla's active document, language, current user, and database dependencies into the delegated native view and its empty model.

## 4.1.0 — 2026-09-10

- Added configurable Tag Pages landing content: a native-layout virtual tag root with zero content results by default, Joomla's all-tags view restricted to top-level tags, its unrestricted flat all-tags list, or an empty routing-context page.
- Made the bare SupJx SmartTag Pages menu route a valid unfiltered landing page with standard Joomla page-heading support instead of returning 404.
- Added a Tag Pages menu option to leave root markers independent for underlying-page filtering, while keeping standalone links on the menu route. Updated the context component to 1.1.0.
- Restricted taxonomy breadcrumbs to standalone tag pages (both marker-based and menu-based URLs); faceted category filters now preserve Joomla's category breadcrumb.

- Prevented category context (`catid`) from leaking into canonical standalone tag URLs.
- Removed modal preview context (`tmpl`, `tag_id`, and `tag_preview`) while building standalone tag links.
- Added regression coverage that preserves unrelated query state, including the active language.

## 4.0.0 — 2026-09-08

- Introduced the unified **SuperSoftJx - SmartTag** Joomla package.
- Bundled the existing `fields/tagselect` plugin as the independently enabled **Fields - SuperSoftJx - SmartTag Select**, retaining its `1.7.0` child version and Joomla element identity.
- Converted the standalone system routing plugin into a package child while retaining its Joomla element identity and saved settings.
- Added the new `com_tagrouting` 1.0.0 companion component and **SupJx SmartTag Pages** menu item type.
- Added menu-based and marker-based standalone tag routes with explicit canonical/context separation.
- Replaced the fixed standalone prefix with configurable accepted and canonical routing markers.
- Added strict menu-route boundary discovery with multilingual item preference.
- Added `TagRoute::forTag()` and `FacetedRoute::forTag()` public APIs.
- Added hierarchy-aware standalone and faceted breadcrumbs with a configurable presentation marker.
- Preserved the existing canonical cleanup, grouped faceted filtering, and current-state API.
- Renamed the displayed plugin to **System - SuperSoftJx - SmartTag Routing**.

## 3.0.1 — 2026-09-01

- Moved plugin settings into the standard Plugin tab and corrected Joomla switcher option order.
- Avoided passing readonly nested-set properties by reference to the Joomla database query binder.

## 3.0.0 — 2026-08-31

- Introduced modular canonical cleanup, hierarchical tag pages, and faceted category routing.
