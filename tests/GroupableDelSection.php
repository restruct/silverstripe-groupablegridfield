<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use RuntimeException;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Group fixture for the group-delete tests (#11). Its onBeforeDelete() records what it can still
 * see of its owner, the way a consumer that propagates a delete upstream would, and can veto the
 * delete by throwing.
 *
 * The two statics are test probes: GroupDeleteTest resets them in setUp() AND tearDown(), because
 * SapphireTest restores Config between tests but not plain statics.
 */
class GroupableDelSection extends DataObject implements TestOnly
{
    private static $table_name = 'GroupableDelSection';

    private static $db = [
        'Name' => 'Varchar',
    ];

    private static $has_one = [
        'Source' => GroupableDelSource::class,
    ];

    private static $belongs_many_many = [
        'MMSources' => GroupableDelSource::class . '.MMSections',
    ];

    /** @var array what onBeforeDelete() saw, one entry per delete */
    public static $seenOnBeforeDelete = [];

    /** @var bool when true, onBeforeDelete() throws, as a consumer's veto would */
    public static $vetoDelete = false;

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();

        static::$seenOnBeforeDelete[] = [
            'SourceID' => (int) $this->SourceID,
            'MMSourceIDs' => array_map('intval', $this->MMSources()->column('ID')),
        ];

        if (static::$vetoDelete) {
            # A plain RuntimeException rather than ValidationException: the latter moved namespace in
            # SS6, and handleGroupDelete() catches any Exception the same way
            throw new RuntimeException('Delete vetoed by the group');
        }
    }

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
