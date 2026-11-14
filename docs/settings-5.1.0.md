# SmartTag settings 5.1.x

Package/routing **5.1.6**, component **1.6.2**, Select **1.7.2**.

## One parameter store, two forms

With SmartTag Routing enabled, **Tags > Options > SmartTag Routing** provides
an additional settings tab. The original plugin configuration remains available.
Both forms read/write the **system/tagrouting plugin's existing extension row**.
No parameters are migrated to com_tags, and runtime never reads SmartTag values
from the com_tags component. Disabling the plugin removes the injected tab;
the original plugin settings remain available to enable/configure it again.

The Tags form temporarily names routing fields with `smarttag_` prefixes to
avoid collisions. The native Save button triggers SmartTag's before-save hook:
it starts a transaction, merges those values into the existing plugin params,
and removes the prefixed fields from the Tags extension record. Native Joomla
then stores its own Tags settings. The after-save hook commits both records.
Errors or later vetoes before native storage complete cause rollback (including
a shutdown fallback when Joomla does not dispatch after-save). Unrelated plugin
parameters and native Tags settings are preserved. No installer migration runs.

Editing the tab requires the existing **core.edit on com_plugins** permission,
in addition to Joomla's native Tags Options access/save permissions. Forged
SmartTag settings from an unauthorised user are rejected, not silently saved.

## Markers

Accepted routing markers displays four inline **native Joomla radio switchers**,
using `joomla.form.field.radio.switcher` and its `switcher` stylesheet. Their visible
labels are `!`, `!tag`, `!filter`, and **Custom**, with a compact **Custom marker:**
textbox in its own aligned row, visible only while Custom is enabled. Yes/No state names remain available to screen readers.
The Custom switch controls acceptance independently of the text; switching it off
retains the custom text for later reuse.

The compact field submits an array of switch values, with custom text in its own field; the save
hook converts it to the existing `accepted_markers`, `custom_marker` and
`enable_custom_marker` plugin keys. Hidden legacy values cannot override the custom switch or edited textbox. The canonical marker remains a separate selection with existing
validation/fallback behavior. Empty breadcrumb values remain empty.

The implementation info banner has been removed. Without plugin edit permission,
the entire SmartTag tab is absent rather than displaying disabled controls.

## Descendant defaults

The global **Include descendant tags in filters** setting remains in SmartTag's
own parameters and is editable from either form. It controls category filtering
and the default for standalone additional tag filters.

SmartTag Pages menu > Options > Include descendants in additional filter matching:

- **Use Global (Yes/No)**: inherits the global SmartTag setting; the displayed
  value reflects the current effective default using Joomla's native label.
- **Yes**: include available descendants for this menu.
- **No**: exact matching for this menu.

Existing explicit Yes/No values remain explicit. This does not change native
com_tags Include Children for the base tag.

## Validation

Native local Joomla form tests cover field injection, native list options,
filtering/validation, inheritance labels, permissions and unrelated forms.
Save/rollback tests use connection-local temporary copies of the extensions
table; installed configuration is not modified. No component parameter copies
are retained. These tests exercise native forms and the save event/table path,
not an installed-package browser session.

## Help and Inline Help

The plugin editor has a separate always-visible **How to use** tab with neutral
setup examples and explanations. Tags Options shows one permanent info paragraph,
with a link to the plugin editor's `#attrib-howto` tab. The link navigates in the same browser tab. Joomla's native tab component activates
the URL fragment; no custom navigation script is required.

Individual field descriptions remain available via native Inline Help in both
forms. The guide and short summary do not require Inline Help to be enabled.
