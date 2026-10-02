<?php

namespace Restruct\GgBrowser;

use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - an item in MultiValue mode: its group key lives in a
 * many_many_extraFields column ('Section') of GgBMVSource's Items relation.
 * See GgBSection for why this never loads in a real install.
 *
 * @property string $Title
 */
class GgBMVItem extends DataObject
{
    private static $table_name = 'GgBMVItem';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $belongs_many_many = [
        'Sources' => GgBMVSource::class,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];
}
