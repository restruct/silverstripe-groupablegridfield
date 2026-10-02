<?php

namespace Restruct\GgBrowser;

use Restruct\Silverstripe\GroupableGridfield\GridFieldAddNewDataObjectGroupButton;
use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * BROWSER-TEST FIXTURE ONLY - the source record of a DataObject-mode grid: its Items are grouped
 * by their SectionID into its Sections, set up the way the README's DataObject-mode example does.
 * See GgBSection for why this never loads in a real install.
 *
 * Specs do not rely on seeded rows: each one asks GgBResetAdmin to (re)create its own source record
 * first, so a spec always starts from the same groups and items, also under --repeat-each.
 *
 * @property string $Title
 * @property string $Setup 'immediate' (default), 'deferred' (OrderableRows::setImmediateUpdate(false))
 *                   or 'modal' (the add-group button without its inline input)
 */
class GgBDOSource extends DataObject
{
    private static $table_name = 'GgBDOSource';

    private static $singular_name = 'DO source';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Setup' => 'Varchar(20)',
    ];

    private static $has_many = [
        'Items' => GgBDOItem::class . '.Source',
        'Sections' => GgBSection::class . '.Source',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        # Only the groupable grid: the scaffolded relation tabs would be a second way to edit the same rows.
        $fields->removeByName(['Items', 'Sections', 'Setup']);
        if (!$this->isInDB()) {
            return $fields;
        }

        $orderable = GridFieldOrderableRows::create('SortOrder');
        if ($this->Setup === 'deferred') {
            # Deferred: drags only change hidden inputs; GridFieldGroupable::handleSave persists them on form save.
            $orderable->setImmediateUpdate(false);
        }

        $groupable = GridFieldGroupable::create('SectionID', 'Section', 'No section')
            ->setGroupsFromRelation('Sections')
            ->setGroupTitleField('Name')
            ->setGroupMetadataFields(['Code' => ['badge' => true]])
            ->setGroupSortField('Sort')
            ->setEditableGroupTitle(true)
            ->setGroupDeleteBehavior('unassign')
            # A custom group action whose effect shows in the re-rendered grid: the Code badge.
            ->addGroupAction('stamp', 'Stamp section', 'font-icon-tick', function ($grid, $source, $group, $data) {
                $group->Code = 'STAMPED';
                $group->write();
                return ['success' => true, 'message' => 'Stamped ' . $group->Name];
            });

        $config = GridFieldConfig::create()
            ->addComponent(GridFieldToolbarHeader::create())
            ->addComponent(GridFieldButtonRow::create('before'))
            ->addComponent(GridFieldDataColumns::create())
            ->addComponent($orderable)
            ->addComponent($groupable)
            ->addComponent(GridFieldAddNewDataObjectGroupButton::create()->setInlineInput($this->Setup !== 'modal'));

        $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $config));

        return $fields;
    }

    /**
     * (Re)create the source record titled so, with three sections and four items:
     * Alpha (A1, A2), Beta (B1), Gamma (empty), and U1 unassigned. Called by GgBResetAdmin.
     */
    public static function reseed(string $title, string $setup = 'immediate'): self
    {
        foreach (self::get()->filter('Title', $title) as $old) {
            foreach ($old->Items() as $item) {
                $item->delete();
            }
            foreach ($old->Sections() as $section) {
                $section->delete();
            }
            $old->delete();
        }

        $source = self::create(['Title' => $title, 'Setup' => $setup]);
        $source->write();

        $sections = [];
        foreach ([['Alpha', 'AL'], ['Beta', 'BE'], ['Gamma', 'GA']] as $i => [$name, $code]) {
            $section = GgBSection::create(['Name' => $name, 'Code' => $code, 'Sort' => $i + 1, 'SourceID' => $source->ID]);
            $section->write();
            $sections[$name] = $section->ID;
        }

        $sort = 1;
        foreach ([['A1', 'Alpha'], ['A2', 'Alpha'], ['B1', 'Beta'], ['U1', null]] as [$itemTitle, $section]) {
            GgBDOItem::create([
                'Title' => $itemTitle,
                'SortOrder' => $sort++,
                'SourceID' => $source->ID,
                'SectionID' => $section ? $sections[$section] : 0,
            ])->write();
        }

        return $source;
    }
}
