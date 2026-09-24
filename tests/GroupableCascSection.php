<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Group fixture that cascade-deletes its items, as a group class that owns its items would. Deleting
 * such a group while items still point at it deletes those items (DataObject::onBeforeDelete() runs
 * the cascade), so 'unassign' mode must unassign them before the group is deleted.
 */
class GroupableCascSection extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableCascSection';

    private static $db = [
        'Name' => 'Varchar',
    ];

    private static $has_one = [
        'Source' => GroupableCascSource::class,
    ];

    private static $has_many = [
        'Items' => GroupableCascItem::class . '.Section',
    ];

    private static $cascade_deletes = [
        'Items',
    ];

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;
    }

    public function canDelete($member = null)
    {
        return true;
    }
}
