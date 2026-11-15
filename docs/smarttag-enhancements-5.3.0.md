# SmartTag Enhancements

Package **5.5.0** adds **SuperSoftJx - SmartTag Enhancements 1.2.0**
(`plg_system_smarttagenhancements`). Routing is **5.1.7**, SmartTag Pages **1.6.2**,
and Tag Select **1.7.2**. The new plugin also installs independently from its
constituent ZIP. It has no dependency on SmartTag Routing or its component.

## Setup

1. Install the package and enable **SuperSoftJx - SmartTag Enhancements**.
2. Keep Joomla's **System - Fields** plugin enabled. It handles the native
   form, request normalization, validation, value storage and deletion lifecycle.
3. Open **Components > SupJx SmartTags**. This menu contains **Tags**, **Fields** and
   **Field Groups**, opening Joomla's native managers in the Tags context.
4. Create a group and fields using the normal Joomla field-type selector.
   Enable the relevant field-type plugins. Choose Backend or Both for Show On
   if the field should appear in the tag editor.
5. Edit a tag and enter its field values in the normal field/group tabs.

No fields, icon/colour conventions, SQL tables, tags or content articles are
created automatically. Definitions and values use native `com_fields` tables
with context `com_tags.tag`; values belong to a tag ID. The plugin does not add
tag-branch assignment or value inheritance. Fields apply to tags according to
native field/group state, access, language and Show On settings.

The warning shown when System - Fields is disabled explains the missing
dependency; this plugin does not silently enable other plugins.

## Permissions

Native Fields/Groups permissions remain under the **com_tags** asset hierarchy.
The plugin adds native rules actions to the field/group forms and adds
**Edit Custom Field Value** to Tags Options > Permissions without modifying
Joomla's access.xml files or copying permissions from another component.

Super Users retain normal access. For other editing groups, grant the relevant
Tags management/edit permissions and **Edit Custom Field Value** at component,
field-group or individual-field level as appropriate. Explicit denials retain
normal Joomla precedence. There is no automatic ACL grant on installation.

The native fields manager enforces definition-level create/edit/state/delete
permissions; FieldsHelper/FieldModel enforces value-edit permissions. Field and
group access levels still control who may retrieve a value.

## Native field types

There is no type-name branching in the plugin. Joomla field plugins create their
own controls, filters, validation, raw-value encoding and prepared output. This
includes repeated values and structured native Subform values. Third-party field
plugins that assume article-specific data may need their own Tags compatibility;
the bridge does not pretend to emulate an article.

Save as Copy and native batch copy preserve accessible, published field values
that the user may edit. Explicitly submitted values, including an intentional
clear, take precedence over copying. Native model access checks remain active.
Moving/reordering a tag preserves its existing ID and therefore its field values.
Native permanent deletion cleans up only fields with the Tags context; article
fields with the same numeric item ID are unaffected. Trashing is not deletion.

## Template/API use

```php
use SuperSoft\Plugin\System\SmartTagEnhancements\TagFields;

$fields = TagFields::get((int) $tag->id);          // prepared native field objects
$named = TagFields::byName((int) $tag->id, false); // raw native field objects

$icon = $named['tag-icon']->rawvalue ?? '';
$colour = $named['tag-colour']->rawvalue ?? '';

TagFields::attach($tag);                         // jcfields keyed by field ID
echo TagFields::render((int) $tag->id);           // native fields layouts/overrides
```

Use the actual, unique field names configured in Joomla. `rawvalue` is native
data, not necessarily a string: it can be multiple values or a structured value.
Escape raw values for their output context (especially HTML attributes); validate
icon/CSS/URL conventions in the template where applicable. Prepared `value` and
`render()` use Joomla's normal field-plugin rendering and layout overrides.

Retrieval checks the real tag record. Anonymous/frontend callers cannot obtain
unpublished, inaccessible or other-language tag metadata. Native field/group
access, state and language filtering also applies. Administrator callers with
Tags management access can inspect unpublished tag metadata as in the tag editor.
Root tag ID 1 and nonexistent tags return no fields.

Native tag pages contain two different objects: tag metadata and the tagged
articles/categories shown beneath it. The plugin never substitutes tag fields
for article fields. Content-prepare events on actual tag metadata can populate
`jcfields`; tagged UCM items keep their original field context.

The stock com_tags tag header does not fire the same display-position events as
an article. Therefore this plugin does not inject automatic before/after-title
markup or replace the site's templates. Call the API in your tag header, tag
chips, cards or overrides as needed. `render()` is explicit manual display of
the retrieved fields, not automatic placement by their Display setting.

## Existing sites and disabling

No Catichisi template or helper-article data is changed. Migrating existing icon
and colour values from helper articles is a separate, reviewable task. Do not
remove those articles before updating and verifying the template's read source.

Disabling/uninstalling the bridge does not delete Fields/Groups/values. It removes
its management shortcuts, component Fields service and copy augmentation. Since
Joomla's System - Fields supports generic contexts, already-defined tag fields
may still appear through the native Fields plugin. Unpublish a field/group to
stop using it while retaining its data. Re-enabling the bridge restores its UI.

## Validation

The local Joomla 6.2 integration suite passes **64 checks** using connection-local
temporary tables. It exercises native Field/Group models, tag form and controller,
save/update/clear, required validation, Save as Copy, public batch copy, deletion,
raw/prepared/manual-render APIs, tag/field ACL, language checks, context isolation,
administrator submenu entries and both load orders with Routing.

Tested native types: text, textarea, integer, list, checkboxes, Subform. Structured
values survive copy without custom parsing. Existing installed content and
configuration are not changed by the test. Joomla 5 is an API compatibility
target, not a separately executed runtime in this workspace. Candidate package
installation and browser-level testing of additional field types remain manual.

Tag Fields and Field Groups remember their own list filters, sorting and page sizes. Switching between article fields and tag fields preserves each list independently.

## Frontend tag editing

The same plugin provides `com_tags` tasks `tag.edit`, `tag.save`, `tag.apply` and
`tag.cancel` for existing tags. The editor works independently of SmartBrowser
and SmartTag Routing. Existing tag views keep their current models and layouts.

A template can use a standard Joomla route for an authorised edit link:

```php
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$url = Route::_('index.php?option=com_tags&view=tag&task=tag.edit&id=' . (int) $tag->id
    . '&return=' . rawurlencode(base64_encode(Uri::getInstance()->toString())));
```

Show the link to signed-in users with `core.edit` on `com_tags`, or
`core.edit.own` when they own the tag. The controller checks these permissions
and the tag access level again on every operation. State changes require
`core.edit.state`. Save, Apply and Cancel require a POST and the Joomla session
token. Existing checkout conflicts are handled by the native model. Return URLs
are restricted to the same site.

Custom fields follow their native Show On setting. Choose Both or Site to make
a field available in the frontend editor. Native field validation and Edit Field
Value permissions apply. The editor offers Save, Apply and Cancel. The form
layout can be overridden as `html/layouts/plg_system_smarttagenhancements/edit.php`
in the site template.

The public API class is `SuperSoft\Plugin\System\SmartTagEnhancements\TagFields`.
Its get/byName/attach/render methods keep the existing behaviour.

## Feature switches and Tags Options

Frontend editing and Custom fields can be enabled independently in the plugin
Options. Both default to Yes, preserving existing installations. The same two
switches are available in Tags Options > SmartTag Enhancements. Use the main
Save button there. Both screens edit the plugin parameters. Access to the Tags
Options tab requires Edit permission for Plugins.

Frontend editing controls editor entry and POST operations. Custom fields
controls tag field forms, Fields/Field Groups navigation and tag field retrieval,
including the TagFields API. Disabling it retains definitions and saved values;
re-enabling it restores their availability. Field values still follow native ACL,
language and Show On settings. Deleting a tag continues to clean its field values.

## Frontend editor presentation

The editor uses native Joomla tabs for Tag Details, Publishing and each available
custom field group. Ungrouped fields have their own tab. Empty groups are omitted,
and fields appear once. Joomla supplies the responsive tab/accordion assets.

Save & Close returns to the permitted return URL, Apply saves and stays in the
editor, and Cancel checks in without saving. Button labels belong to the plugin
in English and Greek. Native validation errors retain entered values and reveal
the tab containing the invalid field. The client script uses Joomla validation
and tab APIs without changing edit-link routes or integration popup behaviour.
