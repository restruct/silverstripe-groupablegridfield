# GroupableGridField Enhancement Plan

**Context saved:** 2026-02-11 (before conversation compaction)

## Goal

Extend GroupableGridField to support DataObjects as groups (not just MultiValueField key/value pairs).

## Current Architecture

- **Groups source:** MultiValueField on source record OR static array
- **Groups format:** Key/value pairs (`['key' => 'Name', ...]`)
- **Item assignment:** String field on item (e.g., `Question.SectionID = 'group_key'`)
- **Templates:** GFGroupableDivider (simple) / GFEnhancedGroupableDivider (with inputs)
- **Extension hooks:** Only `onAfterAssignGroupItems` (after drag)

## Proposed Enhancements

### 1. Support DataObjects as Groups

**New parameter:** `$groupSourceType = 'multivalue' | 'dataobject'`

**For DataObject mode:**
- Groups loaded from has_many/many_many relation on source record
- Item assignment stores has_one ID (FK) instead of string key
- Group metadata available (colors, icons, descriptions, etc.)
- Full CRUD on groups as DataObjects

### 2. Enhanced Group Row Rendering

- Pass full group metadata to template (not just key/name)
- Support custom HTML/fields in group headers
- Template variables for group properties

### 3. Action Buttons in Group Rows

- Already has delete button in enhanced template
- Add extensible button system
- Server-side handlers via URL handlers

## Reference Implementation: FUSE DocSys

**Location:** `/Users/mic/Sites/vakwijs-nl/fuse/document_system/src/model/DocSys_DocumentCollection.php`

**Current pattern (lines 915-923):**
```php
GridFieldGroupable::create(
    'Section',           // Item field (string on DocSys_Document)
    $fieldLabels['Section'],
    $fieldLabels['NoSection'],
    null,
    'Sections'           // MultiValueField on DocSys_DocumentCollection
)
```

**Structure:**
- `DocSys_DocumentCollection.Sections` = MultiValueField (key/value pairs)
- `DocSys_Document.Section` = String field storing section key
- Sections are NOT DataObjects, just key/value pairs in MultiValueField

**Limitation:** Can't have metadata per section (colors, descriptions, etc.) without separate DataObjects.

## Key Files

| File | Purpose |
|------|---------|
| `src/GridFieldGroupable.php` | Main component (lines 172-176: group loading) |
| `src/GridFieldAddNewGroupButton.php` | Dynamic group creation |
| `templates/GFGroupableDivider.ss` | Simple divider template |
| `templates/GFEnhancedGroupableDivider.ss` | Enhanced with inputs/buttons |
| `client/js/groupable.js` | JavaScript (lines 56-77: template data) |

## Architecture Changes Needed

1. **Group Loading (GridFieldGroupable::getHTMLFragments)**
   - Detect group source type
   - Load from relation if DataObject mode
   - Serialize with metadata for JS

2. **Item Assignment (handleGroupAssignment)**
   - Store has_one ID instead of string key
   - Handle FK relationship

3. **JavaScript (groupable.js)**
   - Handle richer group data structure
   - Pass metadata to template

4. **Templates**
   - Access to group properties beyond name
   - Extensible button area

## Use Case: Vakwijs KK-Sections

KK-Sections are `DataSyncItem` records with `Type='KK-Section'`. Currently:
- Sections displayed via GroupableGridField
- But "Add Section" can't create new DataSyncItem records
- Need custom button that creates DataSyncItem + links via many_many

## Next Steps

1. Check FUSE document_system for proxy pattern implementation
2. Design backwards-compatible API for DataObject groups
3. Implement as optional mode (preserve existing MultiValueField behavior)
