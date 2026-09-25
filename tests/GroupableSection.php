<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Group fixture for DataObject mode tests — mirrors ELP's KK sections (DataSyncItem):
 * a real record with title, metadata and sort fields.
 */
class GroupableSection extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableSection';

    private static $db = [
        'Name' => 'Varchar',
        'Code' => 'Varchar',
        'Sort' => 'Int',
    ];

    private static $has_one = [
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

    public function canDelete($member = null)
    {
        return true;
    }
}
