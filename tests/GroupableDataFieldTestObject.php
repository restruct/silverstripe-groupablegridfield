<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GroupableDataField;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Fixture proving GroupableDataField works as an actual DB field type
 * (scaffolds its composite column, getValue() returns array|null).
 */
class GroupableDataFieldTestObject extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableDataFieldTestObject';

    private static $db = [
        'Title' => 'Varchar',
        'Data' => GroupableDataField::class,
    ];
}
