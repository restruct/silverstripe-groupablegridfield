<?php

namespace Restruct\Silverstripe\GroupableGridfield\Tests;

use Restruct\Silverstripe\GroupableGridfield\GridFieldGroupable;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\ORM\DB;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Group deletion in DataObject mode (#11): the group is deleted while it is still linked to its
 * owner, and only then unlinked - without resurrecting it, and cleanly abortable by a veto.
 */
class GroupDeleteTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableDelSource::class,
        GroupableDelSection::class,
        GroupableDelItem::class,
    ];

    /** @var GroupableTestController */
    protected $testController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetProbes();

        # Push a controller so GridField::Link() / FieldHolder() resolve (handleGroupDelete renders the grid)
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
        $this->resetProbes();
        parent::tearDown();
    }

    private function resetProbes(): void
    {
        GroupableDelSection::$seenOnBeforeDelete = [];
        GroupableDelSection::$vetoDelete = false;
    }

    /**
     * A source with one group in the given relation and two items assigned to it.
     *
     * @return array [GroupableDelSource, GroupableDelSection, GroupableDelItem, GroupableDelItem]
     */
    private function createFixture(string $relation): array
    {
        $Source = GroupableDelSource::create(['Title' => 'Doc']);
        $Source->write();

        $Section = GroupableDelSection::create(['Name' => 'Section One']);
        $Section->write();
        # has_many: the link is the FK on the group; many_many: a join row
        $Source->$relation()->add($Section);

        $ItemA = GroupableDelItem::create(['Title' => 'A', 'SortOrder' => 1, 'SectionID' => $Section->ID, 'SourceID' => $Source->ID]);
        $ItemA->write();
        $ItemB = GroupableDelItem::create(['Title' => 'B', 'SortOrder' => 2, 'SectionID' => $Section->ID, 'SourceID' => $Source->ID]);
        $ItemB->write();

        return [$Source, $Section, $ItemA, $ItemB];
    }

    private function buildGrid(GroupableDelSource $Source, string $relation, string $deleteMode = 'unassign'): array
    {
        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder'));

        $groupable = GridFieldGroupable::create('SectionID', 'Section', 'No section')
            ->setGroupsFromRelation($relation)
            ->setGroupTitleField('Name')
            ->setGroupDeleteBehavior($deleteMode);
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable];
    }

    private function deleteGroup(GridFieldGroupable $groupable, GridField $grid, int $groupID): array
    {
        $request = new HTTPRequest('POST', 'group_delete', [], []);
        $request->setRouteParams(['GroupID' => (string) $groupID]);

        return json_decode($groupable->handleGroupDelete($grid, $request), true);
    }

    private function joinRowCount(int $sourceID, int $sectionID): int
    {
        return (int) DB::prepared_query(
            'SELECT COUNT(*) FROM "GroupableDelSource_MMSections" WHERE "GroupableDelSourceID" = ? AND "GroupableDelSectionID" = ?',
            [$sourceID, $sectionID]
        )->value();
    }

    public function testHasManyGroupSeesItsOwnerInOnBeforeDelete(): void
    {
        [$Source, $Section] = $this->createFixture('Sections');
        [$grid, $groupable] = $this->buildGrid($Source, 'Sections');

        $response = $this->deleteGroup($groupable, $grid, $Section->ID);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertCount(1, GroupableDelSection::$seenOnBeforeDelete);
        $this->assertSame(
            $Source->ID,
            GroupableDelSection::$seenOnBeforeDelete[0]['SourceID'],
            'onBeforeDelete() must still see the has_one to its owner: the group may not be unlinked before delete() (#11)'
        );
    }

    public function testHasManyGroupDeleteLeavesNoResurrectedOrphan(): void
    {
        [$Source, $Section, $ItemA, $ItemB] = $this->createFixture('Sections');
        [$grid, $groupable] = $this->buildGrid($Source, 'Sections');
        $countBefore = GroupableDelSection::get()->count();

        $response = $this->deleteGroup($groupable, $grid, $Section->ID);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertStringContainsString('2 item(s) unassigned', $response['message']);
        $this->assertNull(GroupableDelSection::get()->byID($Section->ID));
        # HasManyList::remove() after delete() would write() the ID-less record and INSERT it again
        $this->assertSame(
            $countBefore - 1,
            GroupableDelSection::get()->count(),
            'Deleting a has_many group must not re-insert it as an orphan record'
        );
        $this->assertEquals(0, GroupableDelItem::get()->byID($ItemA->ID)->SectionID);
        $this->assertEquals(0, GroupableDelItem::get()->byID($ItemB->ID)->SectionID);
    }

    public function testManyManyGroupSeesItsOwnerAndLosesItsJoinRow(): void
    {
        [$Source, $Section, $ItemA] = $this->createFixture('MMSections');
        [$grid, $groupable] = $this->buildGrid($Source, 'MMSections');
        $this->assertSame(1, $this->joinRowCount($Source->ID, $Section->ID), 'fixture: join row present');

        $response = $this->deleteGroup($groupable, $grid, $Section->ID);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertSame(
            [$Source->ID],
            GroupableDelSection::$seenOnBeforeDelete[0]['MMSourceIDs'],
            'onBeforeDelete() must still resolve its many_many owner (#11)'
        );
        $this->assertNull(GroupableDelSection::get()->byID($Section->ID));
        $this->assertSame(0, $this->joinRowCount($Source->ID, $Section->ID), 'The join row must be removed after the delete');
        $this->assertEquals(0, GroupableDelItem::get()->byID($ItemA->ID)->SectionID);
    }

    public function testVetoInOnBeforeDeleteLeavesGroupLinkedAndItemsAssigned(): void
    {
        [$Source, $Section, $ItemA, $ItemB] = $this->createFixture('Sections');
        [$grid, $groupable] = $this->buildGrid($Source, 'Sections');
        GroupableDelSection::$vetoDelete = true;

        $response = $this->deleteGroup($groupable, $grid, $Section->ID);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Delete vetoed by the group', $response['message']);
        $Reloaded = GroupableDelSection::get()->byID($Section->ID);
        $this->assertNotNull($Reloaded, 'A vetoed delete must leave the group in place');
        $this->assertEquals($Source->ID, $Reloaded->SourceID, 'A vetoed delete must leave the group linked to its owner (#11)');
        $this->assertEquals($Section->ID, GroupableDelItem::get()->byID($ItemA->ID)->SectionID, 'A vetoed delete must leave items assigned');
        $this->assertEquals($Section->ID, GroupableDelItem::get()->byID($ItemB->ID)->SectionID);
    }

    public function testPreventModeRemovesManyManyJoinRowOfAnEmptyGroup(): void
    {
        [$Source] = $this->createFixture('MMSections');
        $Empty = GroupableDelSection::create(['Name' => 'Empty']);
        $Empty->write();
        $Source->MMSections()->add($Empty);
        [$grid, $groupable] = $this->buildGrid($Source, 'MMSections', 'prevent');

        $response = $this->deleteGroup($groupable, $grid, $Empty->ID);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertNull(GroupableDelSection::get()->byID($Empty->ID));
        $this->assertSame(0, $this->joinRowCount($Source->ID, $Empty->ID), "'prevent' mode must not leave an orphaned join row");
    }
}
