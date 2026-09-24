# GroupableGridField Enhancement Plan

**Updated:** 2026-02-11 (comprehensive research completed)

## Goal

Extend GroupableGridField to support:
1. DataObjects as groups (not just MultiValueField key/value pairs)
2. Custom group creation callbacks (for complex creation like FUSE API calls)
3. Rich group row rendering (metadata, HTML, form fields via GridFieldEditableColumns pattern)
4. Action buttons in group rows (via callback system like GridFieldSaveToFuseButton)
5. Group sorting (Sort field on DataObject or relation, inspired by GridFieldOrderableRows)
6. Configurable delete behavior

## Research Summary

### Existing Usages

**FUSE DocSys** (`document_system/src/model/DocSys_DocumentCollection.php:915-923`):
```php
GridFieldGroupable::create(
    'Section',              // String field on DocSys_Document
    $fieldLabels['Section'],
    $fieldLabels['NoSection'],
    null,
    'Sections'              // MultiValueField on DocSys_DocumentCollection
)
```
- Groups: MultiValueField (key/value pairs)
- Item assignment: String field `Section`
- Used with: GridFieldOrderableRows (immediateUpdate=false), GridFieldEditableColumns

**DataHub DevPath** (`coursedata-front/src/Models/DevPath.php:464-470`):
```php
GridFieldGroupable::create(
    'Phase',                // many_many_extraField on DevPath_Courses
    $this->fieldLabel('Phase'),
    'none',
    null,
    'Phases'                // MultiValueField on DevPath
)
```
- Groups: MultiValueField (key/value pairs)
- Item assignment: String field in `many_many_extraFields`
- Used with: GridFieldOrderableRows, GridFieldAddNewGroupButton, GridFieldEditableColumns

### Inspiration Sources

**GridFieldOrderableRows** (`symbiote/silverstripe-gridfieldextensions`):
- Handles ManyManyList, ManyManyThroughList, regular DataList
- Sort field can be on item OR on relation (many_many_extraFields)
- `getSortTable()` detects where sort field lives
- `validateSortField()` checks field exists
- Extension hooks: `onBeforeReorderItems`, `onAfterReorderItems`

**GridFieldEditableColumns** (`symbiote/silverstripe-gridfieldextensions`):
- Closure callbacks for field generation: `'callback' => fn($record, $column, $grid) => TextField::create($column)`
- Array config: `['title' => '...', 'field' => TextField::class]`
- Scaffolds from dbObject if no config
- Handles many_many_extraFields automatically

**GridFieldSaveToFuseButton** (`app/src/Forms/AI/GridFieldSaveToFuseButton.php`):
- Closure handler pattern: `->setSaveHandler(Closure $handler)`
- Handler receives `(GridField $gridField, DataObject $record)`
- Returns `['success' => bool, 'message' => string]`

**Simpler Module** (`restruct/silverstripe-simpler`):
- Vue 3 import map for modern JS
- `GridFieldModalButton` for row actions with modals
- `GridFieldToggleFieldButton` for field cycling
- Consider using for rich group row templates (optional dependency)

### Backwards Compatibility Requirements

All existing patterns MUST continue working unchanged:
```php
// Static groups array
GridFieldGroupable::create('Group', 'Group', '[none]', ['a' => 'Group A'])

// MultiValueField source
GridFieldGroupable::create('Section', 'Section', '[none]', null, 'Sections')

// With many_many_extraFields (DataHub DevPath pattern)
// Phase field exists on relation, not on item
```

---

## Phase 1: DataObject Groups Support (Foundation)

### New Properties

```php
// DataObject mode configuration
protected ?string $groupsRelation = null;       // has_many/many_many relation name
protected string $groupTitleField = 'Title';    // Field on group DataObject for display
protected array $groupMetadataFields = [];      // Extra fields to serialize for template
protected bool $groupFieldIsFK = false;         // Item field stores FK ID (not string)

// Sorting (inspired by GridFieldOrderableRows)
protected ?string $groupSortField = null;       // Sort field on group DataObject or relation
```

### New Fluent Setters

```php
/**
 * Configure groups from a DataObject relation.
 * Automatically sets groupFieldIsFK = true.
 *
 * @param string $relationName has_many/many_many relation on source record
 */
public function setGroupsFromRelation(string $relationName): self
{
    $this->groupsRelation = $relationName;
    $this->groupFieldIsFK = true;
    return $this;
}

/**
 * Set which field on the group DataObject provides the display name.
 */
public function setGroupTitleField(string $field): self
{
    $this->groupTitleField = $field;
    return $this;
}

/**
 * Set additional fields from group DataObject to serialize for template.
 * These become available as {%=o.groupMeta.FieldName%} in JS template.
 */
public function setGroupMetadataFields(array $fields): self
{
    $this->groupMetadataFields = $fields;
    return $this;
}

/**
 * Set the sort field for groups (on DataObject or relation).
 * Inspired by GridFieldOrderableRows::setSortField().
 */
public function setGroupSortField(string $field): self
{
    $this->groupSortField = $field;
    return $this;
}
```

### Usage Example

```php
// DataObject groups from has_many relation
GridFieldGroupable::create('SectionID', 'Section')  // FK field on item
    ->setGroupsFromRelation('Sections')             // has_many on source record
    ->setGroupTitleField('Title')                   // Display field
    ->setGroupMetadataFields(['Code', 'Color'])     // Extras for template
    ->setGroupSortField('Sort');                    // Sort field on Section DataObject
```

### Group Data Structure

**Current (MultiValueField mode):**
```json
{"group_key": "Group Name", "other_key": "Other Name"}
```

**New (DataObject mode):**
```json
{
  "123": {"id": 123, "name": "Group Name", "Code": "ABC", "Color": "#ff0000"},
  "456": {"id": 456, "name": "Other Name", "Code": "XYZ", "Color": "#00ff00"}
}
```

### PHP Changes (getHTMLFragments)

```php
// In getHTMLFragments():
if ($this->groupsRelation && ($form = $grid->getForm()) && ($record = $form->getRecord())) {
    // DataObject mode: load from relation
    $relationName = $this->groupsRelation;
    $groupList = $record->$relationName();

    if ($this->groupSortField) {
        $groupList = $groupList->sort($this->groupSortField);
    }

    $groups = [];
    foreach ($groupList as $group) {
        $groupData = [
            'id' => $group->ID,
            'name' => $group->{$this->groupTitleField},
        ];
        foreach ($this->groupMetadataFields as $field) {
            $groupData[$field] = $group->$field;
        }
        $groups[$group->ID] = $groupData;
    }
} else {
    // Legacy mode: MultiValueField or static array
    $groups = $this->getOption('groupsAvailable');
    if (!$groups && $this->groupsFieldOnSource && ($form = $grid->getForm()) && ($record = $form->getRecord())) {
        $groups = $record->dbObject($this->groupsFieldOnSource)->getValues();
    }
}

$grid->setAttribute('data-groupable-groups', json_encode($groups));
$grid->setAttribute('data-groupable-mode', $this->groupsRelation ? 'dataobject' : 'multivalue');
```

### JavaScript Changes (groupable.js)

```javascript
// In onadd:
var groups = self.getGridField().data('groupable-groups');
var mode = self.getGridField().data('groupable-mode') || 'multivalue';

$.each(groups, function(groupKey, groupData) {
    var data = {
        "groupKey": groupKey,
        "groupName": (mode === 'dataobject') ? groupData.name : (typeof groupData === 'string' ? groupData : groupData),
        "groupMeta": (mode === 'dataobject') ? groupData : {},
        "groupId": (mode === 'dataobject') ? groupData.id : null,
    };
    // ... render template
});
```

### Item Assignment Changes (handleGroupAssignment)

```php
if ($this->groupFieldIsFK) {
    // DataObject mode: store FK ID
    $group_key = $group_key === 'none' ? null : (int) $group_key;
    $item->$groupField = $group_key;  // e.g., $item->SectionID = 123
} else {
    // Legacy mode: store string key
    if ($group_key == 'none') $group_key = '';
    $item->$groupField = $group_key;
}
```

---

## Phase 2: Custom Group Creation & Delete Callbacks

### New Properties

```php
protected ?Closure $groupCreateHandler = null;
protected ?Closure $groupDeleteHandler = null;
protected string $deleteMode = 'unassign';  // 'unassign', 'prevent', 'callback'
```

### New Setters

```php
/**
 * Set custom handler for creating new groups (DataObject mode only).
 *
 * Callback receives: (GridField $gridField, DataObject $sourceRecord, array $groupData)
 * Should return: ['success' => bool, 'group' => DataObject|null, 'message' => string]
 *
 * If not set, creates DataObject directly and adds to relation.
 */
public function setGroupCreateHandler(Closure $handler): self

/**
 * Set behavior when deleting a group.
 *
 * @param string $mode 'unassign' (default), 'prevent', or 'callback'
 * @param Closure|null $handler Required if mode is 'callback'
 *        Receives: (GridField $gridField, DataObject $group, DataList $itemsInGroup)
 *        Returns: ['success' => bool, 'message' => string]
 */
public function setGroupDeleteBehavior(string $mode, ?Closure $handler = null): self
```

### Usage Examples

```php
// Custom creation (FUSE API pattern)
GridFieldGroupable::create('SectionID', 'Section')
    ->setGroupsFromRelation('Sections')
    ->setGroupCreateHandler(function($gridField, $record, $groupData) {
        // Custom logic: create via FUSE API
        $section = FuseService::createSection($record, $groupData['name']);
        return [
            'success' => (bool)$section,
            'group' => $section,
            'message' => $section ? 'Section created' : 'Failed to create section'
        ];
    });

// Delete: prevent if items exist
GridFieldGroupable::create('SectionID', 'Section')
    ->setGroupsFromRelation('Sections')
    ->setGroupDeleteBehavior('prevent');

// Delete: custom callback (e.g., archive instead of delete)
GridFieldGroupable::create('SectionID', 'Section')
    ->setGroupsFromRelation('Sections')
    ->setGroupDeleteBehavior('callback', function($gridField, $group, $itemsInGroup) {
        $group->IsArchived = true;
        $group->write();
        return ['success' => true, 'message' => 'Section archived'];
    });
```

### New GridField Component: GridFieldAddNewDataObjectGroupButton

```php
class GridFieldAddNewDataObjectGroupButton implements
    GridField_HTMLProvider,
    GridField_ActionProvider
{
    protected string $targetFragment = 'buttons-before-right';
    protected string $buttonTitle = 'Add Group';

    public function getHTMLFragments($grid)
    {
        $groupable = $grid->getConfig()->getComponentByType(GridFieldGroupable::class);
        if (!$groupable || !$groupable->getGroupsRelation()) {
            throw new Exception('GridFieldAddNewDataObjectGroupButton requires GridFieldGroupable in DataObject mode');
        }

        // Render button that triggers modal or inline input
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        $groupable = $gridField->getConfig()->getComponentByType(GridFieldGroupable::class);
        $record = $gridField->getForm()->getRecord();

        $groupData = ['name' => $data['GroupName'] ?? 'New Group'];

        if ($handler = $groupable->getGroupCreateHandler()) {
            $result = $handler($gridField, $record, $groupData);
        } else {
            // Default: create DataObject directly
            $relationName = $groupable->getGroupsRelation();
            $relationList = $record->$relationName();
            $groupClass = $relationList->dataClass();

            $group = $groupClass::create();
            $group->{$groupable->getGroupTitleField()} = $groupData['name'];
            $group->write();
            $relationList->add($group);

            $result = ['success' => true, 'group' => $group, 'message' => 'Group created'];
        }

        // Return response...
    }
}
```

---

## Phase 3: Group Sorting (inspired by GridFieldOrderableRows)

### Sort Table Detection

```php
/**
 * Gets the table which contains the group sort field.
 * Adapted from GridFieldOrderableRows::getSortTable().
 */
public function getGroupSortTable(SS_List $groupList): string
{
    $field = $this->groupSortField;

    if ($groupList instanceof ManyManyList) {
        $extra = $groupList->getExtraFields();
        if ($extra && array_key_exists($field, $extra)) {
            return $groupList->getJoinTable();
        }
    } elseif ($groupList instanceof ManyManyThroughList) {
        $manipulator = $this->getManyManyInspector($groupList);
        $fieldTable = DataObject::getSchema()->tableForField(
            $manipulator->getJoinClass(),
            $field
        );
        if ($fieldTable) {
            return $fieldTable;
        }
    }

    // Field is on the DataObject itself
    $classes = ClassInfo::dataClassesFor($groupList->dataClass());
    foreach ($classes as $class) {
        if (singleton($class)->hasDataBaseField($field)) {
            return DataObject::getSchema()->tableName($class);
        }
    }

    throw new Exception("Couldn't find group sort field '$field'");
}
```

### URL Handler for Group Reordering

```php
private static $allowed_actions = [
    'handleGroupAssignment',
    'handleGroupReorder',     // NEW
    'handleGroupCreate',      // NEW
    'handleGroupDelete',      // NEW
    'handleGroupAction',      // NEW (for custom actions)
];

public function getURLHandlers($grid)
{
    return [
        'POST group_assignment' => 'handleGroupAssignment',
        'POST group_reorder' => 'handleGroupReorder',
        'POST group_create' => 'handleGroupCreate',
        'POST group_delete' => 'handleGroupDelete',
        'POST group_action/$GroupID/$Action' => 'handleGroupAction',
    ];
}
```

---

## Phase 4: Action Buttons in Group Rows

### New Properties

```php
protected array $groupActions = [];
```

### New Methods

```php
/**
 * Add action button to group rows.
 * Inspired by GridFieldSaveToFuseButton pattern.
 *
 * @param string $name Action identifier
 * @param string $icon Font icon class (e.g., 'font-icon-sync')
 * @param string $title Button title/tooltip
 * @param Closure $handler Handler receives: (GridField, DataObject $group, DataObject $source)
 *                         Returns: ['success' => bool, 'message' => string, 'redirect' => string|null]
 */
public function addGroupAction(string $name, string $icon, string $title, Closure $handler): self
{
    $this->groupActions[$name] = [
        'icon' => $icon,
        'title' => $title,
        'handler' => $handler,
    ];
    return $this;
}

/**
 * Remove a group action.
 */
public function removeGroupAction(string $name): self
{
    unset($this->groupActions[$name]);
    return $this;
}
```

### Usage Example

```php
GridFieldGroupable::create('SectionID', 'Section')
    ->setGroupsFromRelation('Sections')
    ->addGroupAction('sync', 'font-icon-sync', 'Sync to FUSE', function($grid, $group, $source) {
        FuseService::syncSection($group);
        return ['success' => true, 'message' => 'Synced'];
    })
    ->addGroupAction('edit', 'font-icon-edit', 'Edit Section', function($grid, $group, $source) {
        return ['redirect' => $group->CMSEditLink()];
    });
```

### Template for DataObject Mode

New template `GFDataObjectGroupableDivider.ss`:
```html
<script type="text/x-tmpl" class="ss-gridfield-inline-new ss-gridfield-groupable-divider-template" id="groupable_divider_template">
    <tr class="groupable-bound groupable-dataobject-bound" data-group-id="{%=o.groupId%}">
        <td class="col-reorder">
            <div class="handle ui-sortable-handle"><i class="icon font-icon-drag-handle"></i></div>
        </td>
        <td colspan="$ColSpan">
            <span class="boundary-indicator">&darr;</span>
            <strong class="group-title">{%=o.groupName%}</strong>
            {% if (o.groupMeta.Code) { %}<span class="badge badge-secondary ml-2">{%=o.groupMeta.Code%}</span>{% } %}

            <div class="group-actions float-right">
                $GroupActionsHTML
            </div>
        </td>
    </tr>
</script>
```

---

## Phase 5: Inline Editing (inspired by GridFieldEditableColumns)

### Integration Options

**Option A: Piggyback on GridFieldEditableColumns**
- Detect if GridFieldEditableColumns is present
- Let it handle the field rendering
- We just handle the group assignment

**Option B: Similar API for group row fields**
```php
->setGroupEditableFields([
    'Title' => ['callback' => fn($group, $column, $grid) => TextField::create($column)],
    'Code' => ['title' => 'Code', 'field' => ReadonlyField::class],
])
```

**Recommended: Option A** (leverage existing component, less code duplication)

---

## Extension Hooks

Following SilverStripe patterns (like GridFieldOrderableRows):

```php
// In handleGroupAssignment
$this->extend('onBeforeAssignGroupItems', $list, $item, $group_key);
$this->extend('onAfterAssignGroupItems', $list, $item, $group_key);

// In handleGroupReorder
$this->extend('onBeforeReorderGroups', $groupList, $sortedIDs);
$this->extend('onAfterReorderGroups', $groupList, $sortedIDs);

// In handleGroupCreate
$this->extend('onBeforeCreateGroup', $record, $groupData);
$this->extend('onAfterCreateGroup', $record, $group, $groupData);

// In handleGroupDelete
$this->extend('onBeforeDeleteGroup', $record, $group, $itemsInGroup);
$this->extend('onAfterDeleteGroup', $record, $group);

// In handleGroupAction
$this->extend('onBeforeGroupAction', $grid, $group, $actionName);
$this->extend('onAfterGroupAction', $grid, $group, $actionName, $result);
```

---

## Optional: Vue/Simpler Integration

If complex group row rendering is needed, can optionally require `restruct/silverstripe-simpler` for:
- Vue 3 templates instead of JS tmpl
- Modal dialogs for group creation/editing
- More sophisticated UI components

However, **default implementation should work without Simpler** using existing JS tmpl pattern for backwards compatibility.

---

## Implementation Order

### Phase 1: DataObject Groups (MVP)
1. Add new properties and fluent setters
2. Modify `getHTMLFragments()` for relation loading
3. Modify `handleGroupAssignment()` for FK storage
4. Update `groupable.js` to handle rich data structure
5. Add data attributes for mode detection
6. Test with FUSE/DataHub to ensure backwards compatibility

### Phase 2: Group Creation
1. Add `$groupCreateHandler` property and setter
2. Create `GridFieldAddNewDataObjectGroupButton` component
3. Add `handleGroupCreate` URL handler
4. Add extension hooks

### Phase 3: Group Sorting
1. Add `$groupSortField` property and setter
2. Port sort table detection from GridFieldOrderableRows
3. Add `handleGroupReorder` URL handler
4. Update JS for group dragging

### Phase 4: Action Buttons
1. Add `$groupActions` array and methods
2. Add `handleGroupAction` URL handler
3. Create `GFDataObjectGroupableDivider.ss` template
4. Add extension hooks

### Phase 5: Delete Handling
1. Add `$deleteMode` and `$groupDeleteHandler`
2. Add `handleGroupDelete` URL handler
3. Update template with delete button
4. Implement 'unassign', 'prevent', 'callback' modes

---

## Files to Modify/Create

| File | Action | Purpose |
|------|--------|---------|
| `src/GridFieldGroupable.php` | Modify | New properties, setters, URL handlers, extension hooks |
| `src/GridFieldAddNewDataObjectGroupButton.php` | Create | DataObject group creation component |
| `client/js/groupable.js` | Modify | Handle rich data structure, action buttons, group reordering |
| `templates/GFDataObjectGroupableDivider.ss` | Create | DataObject mode template with actions |
| `docs/README.md` | Modify | Document new features |

---

## Test Scenarios

1. **Backwards compatibility:**
   - FUSE DocSys with MultiValueField `Sections` - unchanged behavior
   - DataHub DevPath with MultiValueField `Phases` - unchanged behavior

2. **DataObject mode:**
   - Groups load from has_many relation
   - FK assignment stores ID
   - Metadata accessible in template

3. **Custom callbacks:**
   - Group creation via callback
   - Group deletion with 'prevent' mode
   - Group deletion with custom callback

4. **Sorting:**
   - Drag groups to reorder
   - Sort field updates correctly

5. **Action buttons:**
   - Click triggers handler
   - Redirect works
   - Message displayed

---

## Open Questions (Resolved)

1. **Delete handling:** Configurable via `setGroupDeleteBehavior('unassign'|'prevent'|'callback', $handler)`

2. **Sorting groups:** Support Sort field on DataObject or relation (like GridFieldOrderableRows)

3. **Editable fields:** Leverage GridFieldEditableColumns if present, or add similar API

4. **Vue/Simpler:** Optional dependency for enhanced UI, default uses existing JS tmpl
