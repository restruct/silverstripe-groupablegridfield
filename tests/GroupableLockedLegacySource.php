<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use SilverStripe\Dev\TestOnly;

/**
 * A legacy-mode source the current member may view but not edit, while its items stay editable:
 * the item-class permission check passes, so only a check on the SOURCE record can stop a write.
 */
class GroupableLockedLegacySource extends GroupableLegacySource implements TestOnly
{
    private static $table_name = 'GroupableLockedLegacySource';

    public function canEdit($member = null)
    {
        return false;
    }
}
