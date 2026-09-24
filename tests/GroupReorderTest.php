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
 * Group reordering with the group sort field on the group record itself (a has_many). Up to 2.4.1
 * getGroupSortTable() called DataObject::hasOwnTableDatabaseField(), which does not exist, so every
 * such reorder answered "Error reordering groups".
 */
class GroupReorderTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        GroupableDOSource::class,
        GroupableDOItem::class,
        GroupableSection::class,
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

    private function buildGrid(GroupableDOSource $Source): array
    {
        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder'));
        $groupable = GridFieldGroupable::create('SectionID', 'Sectie', 'Geen sectie')
            ->setGroupsFromRelation('Sections')
            ->setGroupTitleField('Name')
            ->setGroupSortField('Sort');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable];
    }

    public function testHandleGroupReorderWithSortFieldOnTheGroup(): void
    {
        $Source = GroupableDOSource::create(['Title' => 'Doc']);
        $Source->write();
        $One = GroupableSection::create(['Name' => 'One', 'Sort' => 1, 'SourceID' => $Source->ID]);
        $One->write();
        $Two = GroupableSection::create(['Name' => 'Two', 'Sort' => 2, 'SourceID' => $Source->ID]);
        $Two->write();
        [$grid, $groupable] = $this->buildGrid($Source);

        $this->assertSame('GroupableSection', $groupable->getGroupSortTable($Source->Sections()));

        $request = new HTTPRequest('POST', 'group_reorder', [], [
            'group_order' => [(string) $Two->ID, (string) $One->ID],
        ]);
        $response = json_decode($groupable->handleGroupReorder($grid, $request), true);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertEquals(1, GroupableSection::get()->byID($Two->ID)->Sort);
        $this->assertEquals(2, GroupableSection::get()->byID($One->ID)->Sort);
    }

    public function testGetGroupTableFindsTheItemClassHoldingTheGroupField(): void
    {
        $Source = GroupableDOSource::create(['Title' => 'Doc']);
        $Source->write();
        [, $groupable] = $this->buildGrid($Source);

        $this->assertSame(GroupableDOItem::class, $groupable->getGroupTable($Source->Items()));
    }
}
