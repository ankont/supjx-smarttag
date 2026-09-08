# Changelog

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
