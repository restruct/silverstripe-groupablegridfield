<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Source fixture for the group-delete tests (#11): the same group class is reachable both as a
 * has_many ('Sections') and as a many_many ('MMSections'), so the delete order can be checked
 * against each relation type.
 */
class GroupableDelSource extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableDelSource';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Items' => GroupableDelItem::class . '.Source',
        'Sections' => GroupableDelSection::class . '.Source',
    ];

    private static $many_many = [
        'MMSections' => GroupableDelSection::class,
    ];

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;
    }
}
