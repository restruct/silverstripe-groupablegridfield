<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Join class of the many_many through relation GroupableCascSource.ThruSections.
 */
class GroupableCascLink extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableCascLink';

    private static $has_one = [
        'Source' => GroupableCascSource::class,
        'Section' => GroupableCascSection::class,
    ];
}
