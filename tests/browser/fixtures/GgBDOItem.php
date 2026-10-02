<?php

namespace Restruct\GgBrowser;

use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - an item in DataObject mode: its group is a real FK (SectionID).
 * See GgBSection for why this never loads in a real install.
 *
 * @property string $Title
 * @property int $SortOrder
 * @property int $SectionID
 * @property int $SourceID
 */
class GgBDOItem extends DataObject
{
    private static $table_name = 'GgBDOItem';

    private static $db = [
        'Title' => 'Varchar(255)',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'Section' => GgBSection::class,
        'Source' => GgBDOSource::class,
    ];

    private static $default_sort = '"SortOrder" ASC, "ID" ASC';

    private static $summary_fields = [
        'Title' => 'Title',
    ];
}
