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
 * A groups method may return a plain DataList instead of a relation list (2.x accepted any SS_List).
 * The group-list parameters are therefore untyped: typing them with Relation made this a TypeError,
 * which handleGroupReorder()/handleGroupDelete() do not catch, so the request failed with a 500.
 */
class GroupListTypeTest extends SapphireTest
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

    private function buildGrid(GroupableCascSource $Source): array
    {
        $config = GridFieldConfig_Base::create();
        $config->addComponent(GridFieldOrderableRows::create('SortOrder'));
        $groupable = GridFieldGroupable::create('SectionID', 'Section', 'No section')
            ->setGroupsFromRelation('FilteredSections')
            ->setGroupTitleField('Name')
            ->setGroupSortField('Sort')
            ->setGroupDeleteBehavior('unassign');
        $config->addComponent($groupable);

        $grid = GridField::create('Items', 'Items', $Source->Items(), $config);
        $form = Form::create($this->testController, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($Source);

        return [$grid, $groupable];
    }

    private function createSource(): array
    {
        $Source = GroupableCascSource::create(['Title' => 'Doc']);
        $Source->write();
        $One = GroupableCascSection::create(['Name' => 'One', 'Sort' => 1, 'SourceID' => $Source->ID]);
        $One->write();
        $Two = GroupableCascSection::create(['Name' => 'Two', 'Sort' => 2, 'SourceID' => $Source->ID]);
        $Two->write();

        return [$Source, $One, $Two];
    }

    public function testReorderWithAPlainDataListAsGroupsSource(): void
    {
        [$Source, $One, $Two] = $this->createSource();
        [$grid, $groupable] = $this->buildGrid($Source);

        $request = new HTTPRequest('POST', 'group_reorder', [], [
            'group_order' => [(string) $Two->ID, (string) $One->ID],
        ]);
        $response = json_decode($groupable->handleGroupReorder($grid, $request), true);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertEquals(1, GroupableCascSection::get()->byID($Two->ID)->Sort);
        $this->assertEquals(2, GroupableCascSection::get()->byID($One->ID)->Sort);
    }

    public function testDeleteWithAPlainDataListAsGroupsSource(): void
    {
        [$Source, $One] = $this->createSource();
        [$grid, $groupable] = $this->buildGrid($Source);

        $request = new HTTPRequest('POST', 'group_delete', [], []);
        $request->setRouteParams(['GroupID' => (string) $One->ID]);
        $response = json_decode($groupable->handleGroupDelete($grid, $request), true);

        $this->assertTrue($response['success'], $response['message'] ?? '');
        $this->assertNull(GroupableCascSection::get()->byID($One->ID));
    }
}
