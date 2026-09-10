# Changelog

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

- Added cleanup for the redundant `view=tags` query on the bare SUPJX Tag Pages menu route.
- Prevented the current language from leaking as a `lang` query parameter in standalone breadcrumb links when no language prefix is needed.
- Restored normal `html/com_tags/tag` template-override discovery for the delegated virtual-root view.
- Updated the independently versioned routing plugin to 4.0.2 and context component to 1.1.3.

## 4.1.2 — 2026-09-10

- Registered the native `com_tags` web-asset registry before rendering the delegated virtual-root tag layout.

## 4.1.1 — 2026-09-10

- Fixed the virtual-root tag view by injecting Joomla's active document, language, current user, and database dependencies into the delegated native view and its empty model.

## 4.1.0 — 2026-09-10

- Added configurable Tag Pages landing content: a native-layout virtual tag root with zero content results by default, Joomla's all-tags view restricted to top-level tags, its unrestricted flat all-tags list, or an empty routing-context page.
- Made the bare SUPJX Tag Pages menu route a valid unfiltered landing page with standard Joomla page-heading support instead of returning 404.
- Added a Tag Pages menu option to leave root markers independent for underlying-page filtering, while keeping standalone links on the menu route. Updated the context component to 1.1.0.
- Restricted taxonomy breadcrumbs to standalone tag pages (both marker-based and menu-based URLs); faceted category filters now preserve Joomla's category breadcrumb.

- Prevented category context (`catid`) from leaking into canonical standalone tag URLs.
- Removed modal preview context (`tmpl`, `tag_id`, and `tag_preview`) while building standalone tag links.
- Added regression coverage that preserves unrelated query state, including the active language.

## 4.0.0 — 2026-09-08

- Introduced the unified **SuperSoftJx - SmartTag** Joomla package.
- Bundled the existing `fields/tagselect` plugin as the independently enabled **Fields - SuperSoftJx - SmartTag Select**, retaining its `1.7.0` child version and Joomla element identity.
- Converted the standalone system routing plugin into a package child while retaining its Joomla element identity and saved settings.
- Added the new `com_tagrouting` 1.0.0 companion component and **SUPJX Tag Pages** menu item type.
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
