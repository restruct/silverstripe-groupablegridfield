<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * handleGroupAssignment()'s item-drop branch on a many_many list with the group field in its
 * extraFields writes the SOURCE record's join table, so it must check canEdit() on that record, as
 * the boundary-drag branch does (BoundaryDragPermissionTest) - not only canView() on the item class.
 */
class ItemDropPermissionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableLegacySource::class,
        GroupableLockedLegacySource::class,
        GroupableLegacyItem::class,
    ];

    /** @var GroupableTestController */
    protected $testController;

    protected function setUp(): void
    {
        parent::setUp();

        $request = new HTTPRequest('GET', '/');
        $request->setSession(new Session([]));
        $this->testController = GroupableTestController::create();
        $this->testController->setRequest($request);
        $this->testController->pushCurrent();

        $this->logInWithPermission('ADMIN');
    }

    protected function tearDown(): void
    {
        $this->testController->popCurrent();
        parent::tearDown();
    }

    /**
     * A source with one item in group sec_a
     */
    private function createSource(string $class): array
    {
        $Source = $class::create(['Title' => 'Map']);
        $Source->Sections = ['sec_a' => 'Alpha', 'sec_b' => 'Beta'];
        $Source->write();
        $Item = GroupableLegacyItem::create(['Title' => 'Doc']);
        $Item->write();
        $Source->Items()->add($Item, ['Section' => 'sec_a', 'SortOrder' => 1]);

        return [$Source, $Item];
    }

    /**
     * Drop $Item into sec_b, the request the JS sends when an item row is dragged to another group.
     * Returns the HTTP status of a refusal, or null when the handler did not refuse.
     */
    private function dropItem(GroupableLegacySource $Source, GroupableLegacyItem $Item, bool $withFormRecord = true): ?int
    {
        $config = GridFieldConfig_Base::create();
        # Not immediate: the forwarded OrderableRows::handleReorder would answer 400 on this request,
        # which carries no sort data, and hide whether the assignment itself was refused
        $orderable = GridFieldOrderableRows::create('SortOrder');
        $orderable->setImmediateUpdate(false);
        $config->addComponent($orderable);
        $groupable = GridFieldGroupable::create('Section', 'Sectie', 'Overig', null, 'Sections');
        $config->addComponent($groupable);
        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        if ($withFormRecord) {
            $form->loadDataFrom($Source);
        }

        $request = new HTTPRequest('POST', 'group_assignment', [], [
            'groupable_item_id' => (string) $Item->ID,
            'groupable_group_key' => 'sec_b',
        ]);

        try {
            $groupable->handleGroupAssignment($grid, $request);
        } catch (HTTPResponse_Exception $e) {
            return $e->getResponse()->getStatusCode();
        }

        return null;
    }

    private function sectionOf(string $class, int $sourceID, int $itemID): ?string
    {
        return $class::get()->byID($sourceID)->Items()->byID($itemID)->Section;
    }

    public function testItemDropOnAnEditableSourceWritesTheJoinRow(): void
    {
        [$Source, $Item] = $this->createSource(GroupableLegacySource::class);

        $this->assertNull($this->dropItem($Source, $Item));
        $this->assertSame('sec_b', $this->sectionOf(GroupableLegacySource::class, $Source->ID, $Item->ID));
    }

    public function testItemDropOnANonEditableSourceIsRefused(): void
    {
        [$Source, $Item] = $this->createSource(GroupableLockedLegacySource::class);
        $this->assertFalse($Source->canEdit());

        $status = $this->dropItem($Source, $Item);

        $this->assertSame(403, $status, 'An item drop must be refused when the source record is not editable');
        $this->assertSame(
            'sec_a',
            $this->sectionOf(GroupableLockedLegacySource::class, $Source->ID, $Item->ID),
            'The join row of a source the member cannot edit must be left unchanged'
        );
    }

    public function testItemDropWithoutAFormRecordIsRefused(): void
    {
        [$Source, $Item] = $this->createSource(GroupableLegacySource::class);

        $status = $this->dropItem($Source, $Item, withFormRecord: false);

        $this->assertSame(403, $status, 'Without a form record the join-table write cannot be checked, so it is refused');
        $this->assertSame('sec_a', $this->sectionOf(GroupableLegacySource::class, $Source->ID, $Item->ID));
    }
}
