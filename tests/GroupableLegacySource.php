<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;

/**
 * Source fixture for MultiValue (legacy) mode tests — mirrors FUSE's DocSys_DocumentCollection:
 * groups are key→name pairs in a MultiValueField ('Sections'), items are a many_many whose group
 * key lives in a many_many_extraFields column ('Section').
 */
class GroupableLegacySource extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableLegacySource';

    private static $db = [
        'Title' => 'Varchar',
        'Sections' => MultiValueField::class,
    ];

    private static $many_many = [
        'Items' => GroupableLegacyItem::class,
    ];

    private static $many_many_extraFields = [
        'Items' => [
            'Section' => 'Varchar',
            'SortOrder' => 'Int',
        ],
    ];

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;
    }

    public function canCreate($member = null, $context = [])
    {
        return true;
    }
}
