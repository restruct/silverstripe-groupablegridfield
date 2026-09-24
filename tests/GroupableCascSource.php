<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Source fixture for the group-delete order tests: groups reachable as a has_many ('Sections') and
 * as a many_many THROUGH a join class ('ThruSections'), with a group class that cascade-deletes its
 * items (see GroupableCascSection).
 */
class GroupableCascSource extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableCascSource';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Items' => GroupableCascItem::class . '.Source',
        'Sections' => GroupableCascSection::class . '.Source',
    ];

    private static $many_many = [
        # many_many through: removing the link deletes a GroupableCascLink record, not a plain join row
        'ThruSections' => [
            'through' => GroupableCascLink::class,
            'from' => 'Source',
            'to' => 'Section',
        ],
    ];

    /**
     * A groups "relation" that is a plain filtered DataList, not a relation list: the shape a
     * consumer gets from Section::get()->filter(...). It must work as a groups source like on 2.x.
     */
    public function FilteredSections()
    {
        return GroupableCascSection::get()->filter('SourceID', $this->ID);
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;
    }
}
