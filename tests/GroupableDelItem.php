<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Item fixture for the group-delete tests (#11): membership is an FK to the group.
 */
class GroupableDelItem extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableDelItem';

    private static $db = [
        'Title' => 'Varchar',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'Section' => GroupableDelSection::class,
        'Source' => GroupableDelSource::class,
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
