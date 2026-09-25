<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Item fixture for DataObject mode tests — mirrors ELP's Questions:
 * group membership is a real FK (SectionID) on the item itself.
 */
class GroupableDOItem extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableDOItem';

    private static $db = [
        'Title' => 'Varchar',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'Section' => GroupableSection::class,
        'Source' => GroupableDOSource::class,
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
