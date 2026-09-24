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
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Group deletion in 'unassign' mode against a group class that cascade-deletes its items, and
 * against a many_many through relation. Complements GroupDeleteTest (#11: owner still linked in
 * onBeforeDelete(), veto leaves everything in place).
 */
class GroupDeleteOrderTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableCascSource::class,
        GroupableCascSection::class,
        GroupableCascItem::class,
        GroupableCascLink::class,
    ];

    /** @var GroupableTestController */
    protected $testController;

    protected function setUp(): void
    {
        parent::setUp();

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
        parent::tearDown();
    }

    /**
     * A source with one group in the given relation and two items assigned to it.
     *
     * @return array [GroupableCascSource, GroupableCascSection, GroupableCascItem, GroupableCascItem]
     */
    private function createFixture(string $relation): array
    {
        $Source = GroupableCascSource::create(['Title' => 'Doc']);
        $Source->write();

        $Section = GroupableCascSection::create(['Name' => 'Section One']);
        $Section->write();
        $Source->$relation()->add($Section);

        $ItemA = GroupableCascItem::create(['Title' => 'A', 'SortOrder' => 1, 'SectionID' => $Section->ID, 'SourceID' => $Source->ID]);
        $ItemA->write();
        $ItemB = GroupableCascItem::create(['Title' => 'B', 'SortOrder' => 2, 'SectionID' => $Section->ID, 'SourceID' => $Source->ID]);
        $ItemB->write();

        return [$Source, $Section, $ItemA, $ItemB];
    }

    private function deleteGroup(GroupableCascSource $Source, string $relation, int $groupID): array
    {
        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder'));
        $groupable = GridFieldGroupable::create('SectionID', 'Section', 'No section')
            ->setGroupsFromRelation($relation)
            ->setGroupTitleField('Name')
            ->setGroupDeleteBehavior('unassign');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        $request = new HTTPRequest('POST', 'group_delete', [], []);
        $request->setRouteParams(['GroupID' => (string) $groupID]);

        return json_decode($groupable->handleGroupDelete($grid, $request), true);
    }

    public function testUnassignModeKeepsItemsOfACascadeDeletingGroup(): void
    {
        [$Source, $Section, $ItemA, $ItemB] = $this->createFixture('Sections');

        $response = $this->deleteGroup($Source, 'Sections', $Section->ID);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertStringContainsString('2 item(s) unassigned', $response['message']);
        $this->assertNull(GroupableCascSection::get()->byID($Section->ID), 'The group must be deleted');
        # Deleting the group while its items still point at it runs its cascade_deletes over them
        $this->assertNotNull(GroupableCascItem::get()->byID($ItemA->ID), "'unassign' mode must not delete the group's items");
        $this->assertNotNull(GroupableCascItem::get()->byID($ItemB->ID), "'unassign' mode must not delete the group's items");
        $this->assertEquals(0, GroupableCascItem::get()->byID($ItemA->ID)->SectionID);
        $this->assertEquals(0, GroupableCascItem::get()->byID($ItemB->ID)->SectionID);
    }

    public function testManyManyThroughGroupLosesItsJoinRecord(): void
    {
        [$Source, $Section, $ItemA] = $this->createFixture('ThruSections');
        $this->assertSame(1, GroupableCascLink::get()->filter('SectionID', $Section->ID)->count(), 'fixture: join record present');

        $response = $this->deleteGroup($Source, 'ThruSections', $Section->ID);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertNull(GroupableCascSection::get()->byID($Section->ID), 'The group must be deleted');
        $this->assertSame(
            0,
            GroupableCascLink::get()->filter('SectionID', $Section->ID)->count(),
            'The many_many through join record must be removed with the group'
        );
        $this->assertEquals(0, GroupableCascItem::get()->byID($ItemA->ID)->SectionID);
    }
}
