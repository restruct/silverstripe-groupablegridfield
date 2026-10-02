<?php

namespace Restruct\GgBrowser;

use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - a group record for DataObject mode (see GgBDOSource).
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6 (no class imports that moved between the two).
 *
 * @property string $Name
 * @property string $Code
 * @property int $Sort
 */
class GgBSection extends DataObject
{
    # Short table names throughout: no namespaced defaults, MySQL caps table names at 64 characters.
    private static $table_name = 'GgBSection';

    private static $db = [
        'Name' => 'Varchar(255)',
        'Code' => 'Varchar(50)',
        'Sort' => 'Int',
    ];

    private static $has_one = [
        'Source' => GgBDOSource::class,
    ];

    private static $default_sort = '"Sort" ASC, "ID" ASC';
}
