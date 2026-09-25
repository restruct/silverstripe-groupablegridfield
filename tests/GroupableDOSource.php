<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Source fixture for DataObject mode tests — mirrors ELP's KK docs:
 * groups come from a relation of real DataObjects (Sections), items hold an FK to their group.
 */
class GroupableDOSource extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableDOSource';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Items' => GroupableDOItem::class . '.Source',
        'Sections' => GroupableSection::class . '.Source',
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
