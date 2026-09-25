<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Item fixture for the group-delete order tests: membership is an FK to the group.
 */
class GroupableCascItem extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableCascItem';

    private static $db = [
        'Title' => 'Varchar',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'Section' => GroupableCascSection::class,
        'Source' => GroupableCascSource::class,
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
