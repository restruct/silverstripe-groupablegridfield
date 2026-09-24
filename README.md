# SilverStripe GridField Groupable

A powerful GridField component that enables drag-and-drop grouping of items. Items can be organized into visual groups with reorderable group boundaries, metadata display, and optional inline editing.

**Key features:**
- Drag items between groups
- Drag entire groups (with their items) to reorder
- Two modes: **MultiValue** (groups as key→name pairs in a MultiValueField on the source record) and **DataObject** (database-backed groups)
- DataObject mode supports: group creation, deletion, reordering, metadata display, custom actions, and inline title editing
- Soft refresh preserves unsaved GridFieldEditableColumns changes
- Works on top of GridFieldOrderableRows (**required** — the component raises a warning without it, and immediate-vs-deferred saving is derived from it, see below)

<img width="784" height="559" alt="groupable" src="https://github.com/user-attachments/assets/caeca7e8-cc46-4c5d-9b54-93d92f4ba6a6" />

## Version Compatibility

| Branch   | Module Version | Silverstripe    | PHP            |
|----------|----------------|-----------------|----------------|
| `master` | `3.x`          | ^5.0 \|\| ^6.0  | ^8.1           |
| `v2`     | `2.x`          | ^4.0 \|\| ^5.0  | ^8.1           |
| `v1`     | `1.x`          | ^4.0            | ^7.4 \|\| ^8.0 |
| -        | `0.x`          | ^3.0            | ^5.6 \|\| ^7.0 |

**Note:** `composer.json` is the source of truth for exact version constraints.

## Installation

```bash
composer require restruct/silverstripe-groupable-gridfield
```

## Usage

### MultiValue Mode (MultiValueField Groups)

*(previously referred to as "legacy mode" — it is not deprecated, just the original lightweight mode; the grid attribute is `data-groupable-mode="multivalue"`)*

Groups are stored as key→name pairs in a MultiValueField on the source record (requires `symbiote/silverstripe-multivaluefield`). Good for simple use cases where groups don't need their own database records.

The **group field on items** may be a plain DB field on the item, **or a `many_many_extraFields` column** on the relation between source and items — both are supported transparently (assignments are written via `ManyManyList::add()` when the group field is an extra field).

```php
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewGroupButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

$config = GridFieldConfig::create()
    ->addComponent(new GridFieldOrderableRows())
    ->addComponent(new GridFieldAddNewGroupButton('buttons-before-right'))
    ->addComponent(new GridFieldGroupable(
        'Phase',                        // Field on items holding group key (or many_many extra field)
        $this->fieldLabel('Phase'),     // Label for group field
        'none',                         // Name for "unassigned" group
        null,                           // Static groups array (null = use MultiValueField)
        'Phases'                        // MultiValueField name on source record
    ));
```

**Add/edit/remove groups in the grid** requires the `GridFieldAddNewGroupButton` component: when it is present
(and renders for the current user), `GridFieldGroupable` automatically switches the divider rows to the
*enhanced* template with editable group-name inputs and a per-group remove button, persisted on form save.
Without the button the grid renders plain display-only dividers — remove the button for readonly users to
get exactly that. Component order does not matter (since 2.4; before that the button had to be added
**before** `GridFieldGroupable`).

### DataObject Mode (Database-Backed Groups)

Groups are DataObjects with their own database records. Enables rich functionality: metadata display, group reordering, custom actions, and inline title editing.

```php
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewDataObjectGroupButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

$config = GridFieldConfig::create()
    ->addComponent(new GridFieldOrderableRows('SortOrder'))
    ->addComponent(new GridFieldGroupable(
        'SectionID',                    // FK field on items (e.g., has_one relation)
        'Section',                      // Label for group field
        'No Section'                    // Name for "unassigned" group
    ))
    ->setGroupsFromRelation('Sections') // Relation method returning groups
    ->setGroupTitleField('Name')        // Field on group DO for display name
    ->setGroupMetadataFields(['Code', 'Description'])  // Extra fields for display
    ->setGroupSortField('Sort')         // Enable group reordering
    ->setEditableGroupTitle(true)       // Enable click-to-edit titles
    ->setGroupDeleteBehavior('unassign') // What happens when group is deleted
    ->addComponent(new GridFieldAddNewDataObjectGroupButton());
```

## Configuration Options

### Basic Options

| Method | Description |
|--------|-------------|
| `setSoftRefresh(bool)` | Preserve unsaved EditableColumns edits (default: true) |

**Immediate vs deferred saving** is configured on `GridFieldOrderableRows::setImmediateUpdate(bool)` — NOT
on this component. `GridFieldGroupable` derives its save behaviour from the OrderableRows component (both
the JS and the PHP save paths), so the two can never get out of sync:
- *immediate* (OrderableRows default): drag-assignments and reorders persist via AJAX right away
- *deferred* (`setImmediateUpdate(false)`): changes are written to per-row hidden inputs and persist when
  the form is saved (via `GridFieldGroupable::handleSave`)

The public `GridFieldGroupable::$immediateUpdate` property is only consulted when no OrderableRows
component is configured (which is unsupported anyway) — do not use it.

### DataObject Mode Options

| Method | Description |
|--------|-------------|
| `setGroupsFromRelation(string)` | Relation method name returning group DataObjects |
| `setGroupTitleField(string)` | Field on group DO for display name (default: 'Title') |
| `setGroupMetadataFields(array)` | Additional fields to display in group row |
| `setGroupSortField(string)` | Field for storing group sort order |
| `setGroupFieldIsFK(bool)` | Whether item field stores FK ID (auto-set by setGroupsFromRelation) |

### Group Creation

```php
// Custom handler for group creation
$groupable->setGroupCreateHandler(function ($gridField, $sourceRecord, $groupData) {
    $group = MyGroupClass::create();
    $group->Name = $groupData['name'];
    $group->write();

    // Add to relation
    $sourceRecord->Groups()->add($group);

    return [
        'success' => true,
        'group' => $group,
        'message' => 'Group created',
    ];
});
```

### Group Deletion

```php
// Options: 'unassign', 'prevent', 'callback'
$groupable->setGroupDeleteBehavior('unassign');

// Custom handler (when mode is 'callback')
$groupable->setGroupDeleteHandler(function ($gridField, $group, $itemsInGroup) {
    // Custom logic
    return ['success' => true, 'message' => 'Deleted'];
});
```

### Custom Group Actions

Add action buttons to group rows:

```php
$groupable->addGroupAction(
    'sync_external',                    // Action name
    'Sync to External System',          // Button title
    'font-icon-sync',                   // Icon class
    function ($gridField, $sourceRecord, $group, $actionData) {
        // $sourceRecord = the record owning the grid, $group = the group DataObject,
        // $actionData = POST vars of the action request
        return [
            'success' => true,
            'message' => 'Synced successfully',
            // 'redirect' => '/some/url',  // Optional redirect
        ];
    }
);
```

**NB:** before 2.4 the *implementation* deviated from this documented signature (it took
`($name, $icon, $title)` and invoked the handler as `($grid, $group, $record)`). Since 2.4 the code
matches the documentation above — if you had written a consumer against the old *code* order,
swap your `$title`/`$icon` arguments and handler parameters.

### Inline Title Editing

Enable click-to-edit for group titles:

```php
$groupable->setEditableGroupTitle(true, function ($gridField, $sourceRecord, $group, $newTitle) {
    $group->Name = $newTitle;
    $group->write();

    return [
        'success' => true,
        'message' => 'Title updated',
    ];
});
```

The title displays as a dashed-border box that matches the input field dimensions. Click to edit, press Enter to save, Escape to cancel.

## Templates

The module includes three templates for group boundary rows (resolved automatically at render time
unless a custom template was set via `setOption('dividerTemplate', ...)`):

- `GFGroupableDivider.ss` - Plain display-only divider for MultiValue mode (no add-group button present)
- `GFEnhancedGroupableDivider.ss` - MultiValue mode with `GridFieldAddNewGroupButton`: editable group-name
  inputs + per-group remove button (persisted on form save)
- `GFDataObjectGroupableDivider.ss` - Rich template for DataObject mode with:
  - Drag handle for group reordering
  - Delete button
  - Custom action buttons
  - Editable title (click-to-edit)
  - Metadata badges
  - Content summary display

Template variables available in DataObject mode:
- `{%=o.groupName%}` - Group display name
- `{%=o.groupId%}` - Group DataObject ID
- `{%=o.groupKey%}` - Group key (ID or legacy key)
- `{%=o.groupMeta.FieldName%}` - Metadata fields
- `{%=o.editableTitle%}` - Whether title is editable

## JavaScript Events

The module uses jQuery entwine for event handling. Key events:

- `addnewgroup` - Triggered when adding a new group (legacy mode)
- `onsort` - Handles drag-drop reordering
- `onfocusout` - Saves inline title edits

**Note:** Entwine doesn't support `onblur` - use `onfocusout` instead.

## Technical Notes

### Soft Refresh

When `softRefresh` is enabled, item reorders are saved via AJAX without refreshing the GridField. This preserves unsaved edits in GridFieldEditableColumns. The request includes full form state via `form.find(':input').serializeArray()`.

### Group Sort Order Preservation

PHP sends groups as an array (not object) to preserve sort order. JavaScript objects with numeric keys get automatically sorted, which would break custom ordering.

### Unassigned ('none') Semantics

The "unassigned" pseudo-group maps per mode when an item is dropped into it:
- **MultiValue mode:** group field is set to `''` (empty string; may roundtrip as `null` from
  many_many extra fields — both mean unassigned)
- **DataObject mode:** FK field is set to `null` (stored as `0`)

The unassigned divider's name input is `disabled` so it never submits — do not "fix" that: submitting it
would create a phantom group entry in the MultiValueField.

### Click-to-Edit Implementation

The editable title uses a click-to-toggle pattern:
1. Visible by default: `<span.group-title-editable>` with dashed border
2. Hidden by default: `<input.group-title-input>` with `.d-none` class
3. On click: wrapper hides, input shows and focuses
4. On blur/Enter: saves via AJAX, updates title text, hides input
5. On Escape: reverts value, hides input without saving

Bootstrap utility classes (`.d-none`, `.d-inline-block`) handle visibility toggling.

## Requirements

- SilverStripe ^4.0 || ^5.0 (2.x branch; 3.x targets ^5.0 || ^6.0)
- symbiote/silverstripe-gridfieldextensions — `GridFieldOrderableRows` is a **hard requirement**:
  `GridFieldGroupable` raises a `user_error` when it is missing, and derives immediate-vs-deferred
  saving from it
- symbiote/silverstripe-multivaluefield — used by MultiValue mode (groups storage) and the
  `GridFieldAddNewGroupButton`
- PHP ^8.1

## Thanks

- [TITLE WEB SOLUTIONS](http://title.dk/) for sponsoring the initial development of this module
