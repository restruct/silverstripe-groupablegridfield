# SilverStripe GridField Groupable
[![Build Status](https://travis-ci.org/restruct/silverstripe-groupable-gridfield.svg?branch=master)](https://travis-ci.org/restruct/silverstripe-groupable-gridfield)
[![codecov.io](https://codecov.io/github/restruct/silverstripe-groupable-gridfield/coverage.svg?branch=master)](https://codecov.io/github/restruct/silverstripe-groupable-gridfield?branch=master)

This module facilitates drag & drop grouping of items in a GridField.   
It bolts on top of- and depends on GridFieldOrderableRows for the drag & drop sorting functionality.  
Allows adding new 'groups' on the fly when configured with a MultiValueField to store them.  
Groups themselves can also be reordered (drag-drop, experimental). 

## NOTE: currently slightly 'WIP'
We found a (Silverstripe 3) project in which quite a lot of development was done on this module which never got published (a.o. group reordering). These updates + additions have now been included + updated in this module but may still need a bit of work/debugging.
- **Updated namespace** `micsck\GroupableGridfield` -> `Restruct\Silverstripe\Gridfield`

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