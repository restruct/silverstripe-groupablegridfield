# SilverStripe GridField Groupable

This module facilitates drag & drop grouping of items in a GridField.
It bolts on top of- and depends on GridFieldOrderableRows for the drag & drop sorting functionality.
Allows adding new 'groups' on the fly when configured with a MultiValueField to store them.
Groups themselves can also be reordered (drag-drop, experimental).

<img width="784" height="559" alt="groupable" src="https://github.com/user-attachments/assets/caeca7e8-cc46-4c5d-9b54-93d92f4ba6a6" />

## Version Compatibility

| Branch | Module Version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `master` | `3.x` | ^6.0 | ^8.3 |
| `v2` | `2.x` | ^4.0 \|\| ^5.0 | ^8.1 |
| `v1` | `1.x` | ^4.0 | ^7.4 \|\| ^8.0 |
| - | `0.x` | ^3.0 | ^5.6 \|\| ^7.0 |

**Note:** `composer.json` is the source of truth for exact version constraints.

## Usage:
```php
$gfConfig = GridFieldConfig::create()
    // setup your config as usual, must include orderable rows
    ->addComponent(new GridFieldOrderableRows())
    // add Groupable + AddNewGroupButton
    ->addComponent(new GridFieldAddNewGroupButton('buttons-before-right'))
    ->addComponent(new GridFieldGroupable(
        'Phase', // field on subjects to hold group key
        $this->fieldLabel('Phase'), // label of group field
        'none', // fallback/unassigned group name
        null, // (fixed) list of available groups (key value), set to null to use MultiValue field instead
        'Phases' // name of MultiValueField on source record to provide groups (allows adding new on-the-fly)
    ));
```

## Thanks

- [TITLE WEB SOLUTIONS](http://title.dk/) for sponsoring the initial development of this module
