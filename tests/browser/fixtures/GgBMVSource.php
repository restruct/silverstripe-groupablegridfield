<?php

namespace Restruct\GgBrowser;

use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;

/**
 * BROWSER-TEST FIXTURE ONLY - the source record of a MultiValue-mode grid: groups are key => name
 * pairs in its Sections MultiValueField, and each item's group key is the 'Section' extra field of
 * the many_many Items relation (the README's MultiValue-mode example).
 * See GgBSection for why this never loads in a real install; see GgBDOSource for the reseeding.
 *
 * @property string $Title
 * @property string $Setup 'immediate' (default), 'deferred' or 'plain' (no add-group button)
 */
class GgBMVSource extends DataObject
{
    private static $table_name = 'GgBMVSource';

    private static $singular_name = 'MV source';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Setup' => 'Varchar(20)',
        'Sections' => MultiValueField::class,
    ];

    private static $many_many = [
        'Items' => GgBMVItem::class,
    ];

    private static $many_many_extraFields = [
        'Items' => [
            'Section' => 'Varchar(100)',
            'SortOrder' => 'Int',
        ],
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        # The groups are edited in the grid's divider rows (GridFieldGroupable::handleSave); a scaffolded
        # Sections field would save the same column a second time.
        $fields->removeByName(['Items', 'Sections', 'Setup']);
        if (!$this->isInDB()) {
            return $fields;
        }

        $orderable = GridFieldOrderableRows::create('SortOrder');
        if ($this->Setup === 'deferred') {
            $orderable->setImmediateUpdate(false);
        }

        $config = GridFieldConfig::create()
            ->addComponent(GridFieldToolbarHeader::create())
            ->addComponent(GridFieldButtonRow::create('before'))
            ->addComponent(GridFieldDataColumns::create())
            ->addComponent($orderable)
            ->addComponent(GridFieldGroupable::create('Section', 'Section', 'none', null, 'Sections'));
        if ($this->Setup !== 'plain') {
            # Added AFTER GridFieldGroupable on purpose: since 2.4 the order must not matter.
            $config->addComponent(GridFieldAddNewGroupButton::create('buttons-before-right'));
        }

        $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $config));

        return $fields;
    }

    /**
     * (Re)create the source record titled so, with groups alpha => Alpha, beta => Beta and four
     * items: I1, I2 in alpha, I3 in beta, I4 unassigned. Called by GgBResetAdmin.
     */
    public static function reseed(string $title, string $setup = 'immediate'): self
    {
        foreach (self::get()->filter('Title', $title) as $old) {
            foreach ($old->Items() as $item) {
                $item->delete();
            }
            $old->delete();
        }

        $source = self::create(['Title' => $title, 'Setup' => $setup]);
        $source->Sections = ['alpha' => 'Alpha', 'beta' => 'Beta'];
        $source->write();

        $sort = 1;
        foreach ([['I1', 'alpha'], ['I2', 'alpha'], ['I3', 'beta'], ['I4', '']] as [$itemTitle, $key]) {
            $item = GgBMVItem::create(['Title' => $itemTitle]);
            $item->write();
            $source->Items()->add($item, ['Section' => $key, 'SortOrder' => $sort++]);
        }

        return $source;
    }
}
