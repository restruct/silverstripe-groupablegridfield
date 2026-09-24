<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Item fixture for MultiValue (legacy) mode tests — mirrors FUSE's DocSys documents:
 * group membership lives in a many_many_extraFields column on the join table (see GroupableLegacySource).
 */
class GroupableLegacyItem extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableLegacyItem';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $belongs_many_many = [
        'Sources' => GroupableLegacySource::class,
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
